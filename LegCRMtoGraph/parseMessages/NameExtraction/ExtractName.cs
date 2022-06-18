using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.RegularExpressions;
using parseMessages.ParsedMessageObjects;
using parseMessages.EmailExtraction;
using parseMessages.PhoneExtraction;
using parseMessages;
using System.Data.SqlClient;


namespace parseMessages.NameExtraction
{
    class ExtractName
    {
        public enum FoundNameType
        {
            unknown, //0
            first, //1
            last, // 2
            both, // 3
            nonName, //4
            initial // 5
        }
        public struct NameStruct
        {
            public string Name;
            public FoundNameType NameType;

        };
        public static void Extract(SqlConnection connection, ref CRMParsedMessageObject message) // void b/c populates message properties
        {
            /* basic model is look at words in areas likely to include the name 
            *      parse out words 
            *      exclude titles, closings 
            *            for what's left test either a common word or as fn ln or ln, fn against voter database
            *
            * not insisting that fn be attached to last name in database, just that they be valid
            * places to look: 
            * 
            *   (A) Reply to personal
            *   (B) From personal
            *   (1) N words ahead of index of found address (N subject to experiment)
            *   (2) last N words
            *   (x) don't do first few words -- too likely that could be greeting (or even list of addressees); too complex to exclude this
            *   
            * overall, want to be conservative about determining name -- 
            * 
            * don't want non-names in the database or in correspondence
            * 
            * -- scrub bracketed materials, emails, phones, closings, titles             * 
            * -- exclude common words that are non-names
            * -- accept only names that exist in the voter database as fn or ln
            * -- accept them only if both present and they appear in order in an appropriate region
            */

            if ( ! string.IsNullOrEmpty(message.reply_to_personal))
            {
                string reversedReply = ReverseName(message.reply_to_personal);// handle "last, first" construction 
                List<NameStruct> namesList = NamesFromString(
                    connection, 
                    "NOMATCH" == reversedReply ? message.reply_to_personal : reversedReply
                );
                NamesFromList(ref message, namesList);
                if (!String.IsNullOrEmpty(message.last_name))
                {
                    return; // have both first and last, since don't populate last without first
                }
            }
            message.first_name = string.Empty; // restart if partial success; belt and suspenders

            if (!String.IsNullOrEmpty(message.from_personal))
            {
                string reversedFrom = ReverseName(message.from_personal);// handle "last, first" construction 
                List<NameStruct> namesList = NamesFromString(
                    connection,
                    "NOMATCH" == reversedFrom ? message.from_personal : reversedFrom
                    );
                NamesFromList(ref message, namesList);
                if (!String.IsNullOrEmpty(message.last_name))
                {
                    return; // have both first and last, since don't populate last without first
                }
            }
            message.first_name = string.Empty; // restart if partial success; belt and suspenders
       
            // look for name in last five good words before address
            if (message.AddressIndex > 2) // only protecting against out of range condition; has to be much higher if found
            {
                List<NameStruct> namesList = NamesFromString(
                    connection, 
                    ExtractLastNWords(
                        message.OriginalMessage.uniqueBody.content[0..message.AddressIndex], 
                        5
                        )
                    );
                NamesFromList(ref message, namesList);
            }
            if (message.last_name != string.Empty)
            {
                return;
            }
            else
            {
                message.first_name = string.Empty; // restart if partial success; belt and suspenders
            }

            // look for name in last five good words of message if have not real
            if ( String.IsNullOrEmpty(message.first_name))  // will not populate last name without first name 
            {
                List<NameStruct> namesList = NamesFromString(
                    connection, 
                    ExtractLastNWords(
                        message.OriginalMessage.uniqueBody.content, 
                        5
                        )
                    );
                NamesFromList(ref message, namesList);
            }
        }

        // this function creates a list of name candidates in original order a name type (fn, ln . . . ) appended
        // include non-names, because don't want to knock a non-name out of a series to incorrectly put fn and ln next to each other
        static List<NameStruct> NamesFromString(SqlConnection connection, string searchRegion )
        {
            var namesList = new List<NameStruct>();


            if ( String.IsNullOrEmpty (searchRegion))
            {
                return namesList; // empty
            }


            // split remaining string into an array of strings that may be empty (splitting out everything ex unicode letters and hypen and apostrophe)
            string[] splits = Regex.Split(searchRegion, @"[^\p{L}-']", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2));

            foreach (string nameCandidate in splits)
            {
                // discard the empties (resulting from consecutive pattern matches)
                if (String.IsNullOrEmpty(nameCandidate)) continue;

                // capitalized single letter initials recognized
                if(Regex.IsMatch(nameCandidate, @"^[A-Z]$",RegexOptions.None, TimeSpan.FromSeconds(2))) {
                    NameStruct initial = new NameStruct
                    {
                        Name = nameCandidate,
                        NameType = FoundNameType.initial
                    };
                    namesList.Add(initial);
                    continue;
                }
                try
                {
                    if (
                        // save non names -- cannot validly be in the middle of a full name, but could be bracketed by name words
                        Regex.IsMatch(nameCandidate, $@"^(?:{Terms.CommonWords.Terms})$", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2)) ||
                        // consider anything that is all lower case to be a non name -- rare that people do this, but some common words are uncommon names
                        Regex.IsMatch(nameCandidate, @"^[a-z]+$", RegexOptions.None, TimeSpan.FromSeconds(2))
                        )
                    {
                        NameStruct matchedNonName = new NameStruct
                        {
                            Name = nameCandidate,
                            NameType = FoundNameType.nonName
                        };
                        namesList.Add(matchedNonName);
                        continue;
                    }
                }
                catch (RegexMatchTimeoutException e)
                {
                    Console.Write("Regex timeout in comparison to common words, ExtractName.cs line 173" + e.ToString());
                    // treat exception as not matched -- proceed to do CheckName
                }
                // save name candidates, even if not found
                short dbNameClass = MessageSQLCommands.CheckName(connection, nameCandidate);
                // replace closing with empty
                NameStruct checkedName = new NameStruct
                {
                    Name = nameCandidate,
                    NameType = (FoundNameType)dbNameClass
                };
                namesList.Add(checkedName);
            }
            return namesList;
        }



        // reading in order through list of undiscarded words, knowing type of word 
        static void NamesFromList (ref CRMParsedMessageObject message, List<NameStruct> namesList)
        {
            /* logic summary
             * 
             * if first or initial, 
             *      either use as first or ignore if already have first (guessing is a middle name or initial)
             * if both, 
             *      use as first if don't have first or, if do have first, use as last whether not have last already (if already have last, treating the old last as a middle)
             * if last
             *      use as last if have first; otherwise ignore
             * if non-name or unrecognized, either end search if have ln already or restart search because interrupted phrase
             */


            string foundFirst = String.Empty;
            string foundLast = String.Empty;
            foreach (NameStruct nameTuple in namesList)
            {
                // if don't have a first and hit a possible first, use it;
                // ignore a second found first -- treat as uncaptured middle name or an unhandled fn/ln/fn pattern
                if (
                       FoundNameType.both == nameTuple.NameType ||
                       FoundNameType.first == nameTuple.NameType ||
                       FoundNameType.initial == nameTuple.NameType
                    )
                {
                    if (String.Empty == foundFirst)
                    {
                        foundFirst = nameTuple.Name;
                        continue;
                    }
                }

                // if have a first and hit an initial or another first ignore it (apparent middle name)
                if (
                         FoundNameType.first == nameTuple.NameType ||
                         FoundNameType.initial == nameTuple.NameType
                   )
                {
                    if (String.Empty == foundFirst)
                    {
                        continue;
                    }
                }

                // if have a first and hit a last, use it (might overlay previously found last, considering that a middle name)
                // if have not hit a first, ignore possible last; not trying to handle ln,fn syntax in message body
                if (
                        FoundNameType.both == nameTuple.NameType ||
                        FoundNameType.last == nameTuple.NameType
                    )
                {
                    if (String.Empty != foundFirst)
                    {
                        foundLast = nameTuple.Name;
                        continue;
                    }
                }

                // if get a known non name or unknown (rare word), either reset if in middle or quit as done
                if (
                       FoundNameType.nonName == nameTuple.NameType ||
                       FoundNameType.unknown == nameTuple.NameType
                    )
                {
                    // if it falls in the middle of a name, start over
                    if (string.Empty != foundFirst && string.Empty == foundLast)
                    {
                        foundFirst = string.Empty;
                        continue;
                    }
                    // if have a full name already, consider done
                    else if (string.Empty != foundFirst && string.Empty != foundLast)
                    {
                        break;
                    }
                    // otherwise continue
                    continue;
                }
            }

            // require found both to use one; actually only need to test foundLast
            if (foundFirst != string.Empty && foundLast != string.Empty )
            {
                message.first_name = foundFirst;
                message.last_name = foundLast;
            }
        }

        static string ReverseName (string nameString)
        {
            Match match = Regex.Match(nameString, @"^(\w+),\s?(\w+\b.*)");
            if (match.Success)
            {
                // Blow, Joe (Sen) becomes Joe (Sen) Blow 
                return match.Groups[2].Value + " " + match.Groups[1].Value;
            } else
            {
                return "NOMATCH";
            }
        }

        static string ExtractLastNWords( string inputString,int countWords = 10 )
        {
            // discard bracketed material
            inputString = Regex.Replace(inputString, @"(?:\[[^\]]*\]|<[^>]*>)", " ");

            // discard email address(es)
            inputString = Regex.Replace(inputString, ExtractEmail.emailRegex, " ");

            // discard phone number(s)
            inputString = Regex.Replace(inputString, ExtractPhone.phoneRegex, " ");

            // discard closing -- most likely adjacent to a name; so discarding unlikely to convert an interrupted name to a name
            inputString = Regex.Replace(inputString, $@"\b(?:{Terms.Closings.Terms})\b", string.Empty, RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2));

            // discard titles -- most likely adjacent to a name; so discarding unlikely to convert an interrupted name to a name
            inputString = Regex.Replace(inputString, $@"\b(:?{Terms.Titles.Terms})\b", string.Empty, RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2));

            // split string on spaces (leave punctuation in place), remove empties
            string[] words = inputString.Split(" ", StringSplitOptions.RemoveEmptyEntries);

            // reconstitute in order for last 
            int getLength = Math.Min(countWords, words.Length);
            if (getLength > 0 ) { 
                string[] last10 = words[^getLength..^0]; 
                // Ranges are exclusive, meaning the end isn't included in the range; ^length is an OK index b/c ^1 is last element in array
                return string.Join(" ", last10);
            } else
            {
                return string.Empty;
            }


        }
    }
}
