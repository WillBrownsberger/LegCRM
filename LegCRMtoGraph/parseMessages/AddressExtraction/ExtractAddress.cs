using System;
using System.Collections.Generic;
using System.Text;
using System.Text.RegularExpressions;
using parseMessages.ParsedMessageObjects;
using parseMessages.AddressExtraction.Terms;

namespace parseMessages.AddressExtraction
{
    class ExtractAddress
    {
        public static void Extract ( ref CRMParsedMessageObject message )
        {
            /*
             * 
             * Try as Massachusetts residential address 
             * 
             * POLICY: Better to wrongly find an MA address and wrongly deal with a non-constituent, 
             *      than to reject legit correspondence from constituent
             *      
             * SO: Be soft on finding an MA address -- only adjacent city and state is required 
             * 
             * Number, street, suffix, possible apt, city [known set], state[ma] zip(?) in MA range    
             *
             */

            /*
             * regex components
             */
            // separator simplified 
            string sep = @"[\s,.|]{1,10}"; 
            // street number, if digits, may be followed by up to 4 letters
            string streetNumber = @"\b(?:one|two|three|four|five|six|seven|eight|nine|zero|\d{1,6}[A-Z]{0,4})";
            // zip code
            string maZipCode = @"(?<zip>0[12]\d{3}(?:-?\d{4}\b|\b))";
            string zipCode = @"(?<zip>\d{5}(?:-?\d{4}\b|\b))";

            string addressTag = @"(?:\s*(?:Address|Street Address):?\s*)?";
            string address2Tag = @"(?:\s*(?:Apartment|Street\ Address\ Line\ 2):?\s*)?";
            string cityTag = @"(?:\s*(?:City|City\s*/\s*Town):?\s*)?";
            string stateTag = @"(?:\s*(?:State|State\s*/\s*Province):?\s*)?";
            string zipTag = @"(?:\s*(?:Zip|Zip\ Code|Postal\s*/\s*Zip\ Code):?\s*)?";


            var maAddressRegex = new Regex(
                  $@"
                    # optional street address with two separate capturing groups
                    (:?
                        {addressTag}
                        (?<address_line>
                            {streetNumber}[ ]{{1,2}}
                            # a street name, including designator like avenue
                            (?:
                                # a one word street like fenway
                                (?:{SpecialStreets.Terms})|
                                # or a 1-4 word street, possibly including numbers and ending with a designator
                                # note that the first word could actually be a suffix for the street number
                                (?:[A-Z0-9-]{{1,20}}\.?[ ]{{1,2}}){{1,4}}(?:{Streets.Terms}) # ends with the term, not a space
                            )
                            # end street address capturing group
                        )
                        {address2Tag}
                        (?<apartment_line>
                            # possible apartment # /or building designation, note: no post directionals in MA (NE, SW)
                            {sep}(?:(?:{Apartments.Terms})\.?[ ]{{1,2}})?[A-Z0-9-#]{{1,6}}
                        )?
                    )?
                    {sep}
                    # capturing group 2:  a city name followed maybe by a comma
                    {cityTag}(?<city>{Places.Terms}){sep}
                    # capturing group 3: massachusetts or abbreviation
                    {stateTag}(?<state>MASSACHUSETTS|MASS\.|MASS|MA){sep}
                    # optional capturing group 4 zip code (5 or 9, with or with out hyphen
                    {zipTag}{maZipCode}?
                    ",
                RegexOptions.IgnoreCase | RegexOptions.IgnorePatternWhitespace,
                TimeSpan.FromSeconds(20)
                );

            try
              {
                MatchCollection AllMatches = maAddressRegex.Matches(message.OriginalMessage.uniqueBody.content);
                foreach (Match match in AllMatches )
                {
                    // by pass disqualified match
                    if (CheckDisqualifiers(match.Groups[0].Value)) continue;

                    /*
                     * take first not disqualified match 
                     * 
                     * it is remotely possible that message could contain multiple good addresses
                     * could try to distinguish among them by name extraction, but this rare and handling this case
                     * would require tighter coupling of name and address extraction . . . messy
                     * 
                     */
                    message.address_line = StripExtraWhiteSpace(match.Groups["address_line"].Value) + " " + StripExtraWhiteSpace(match.Groups["apartment_line"].Value);
                    message.city = match.Groups["city"].Value;
                    message.state = "MA"; // standardize state reference
                    message.zip = match.Groups["zip"].Value;
                    message.AddressIndex = match.Index;
                    message.AddressLength = (short)match.Length;
                    break;

                }
            } catch (RegexMatchTimeoutException e)
            {
                // no need to handle exception -- just treat as nonmatch
                Console.Write("Regex timeout in Address Extraction, line 108" + e.ToString());
            }

            // if have populated address, return
            if(!String.IsNullOrEmpty(message.state))
            {
                return;
            }

            /*
             *  no MA address yet
             * 
             *  is the problem that the available addresses are out of state?
             * 
             *  want to be fairly strict in finding an out of state address (don't want to wrongly dq) 
             *  so insist on rough street and zip
             */
            var notMARegex = new Regex(
                    // note, not trying to fully parse out of state streets; not trying to id out of state cities
                     $@"
                    # required NON capturing group 1 begin street address, softly defined
                    (?:
                        {streetNumber}[ ]{{0,2}}
                        # a street name, including designator like avenue
                        (?:
                            # a 1-5 word street, possibly including numbers and ending with a designator
                            # note that the first word could actually be a suffix for the street number
                            (?:[A-Z0-9-]{{1,20}}\.?[ ]{{1,2}}){{1,4}}(?:{Streets.Terms}) # ends with the term, regex needs to go on to supply space
                        )
                        # possible apartment # /or building designation, or post directionals . . . a bunch of short words with possible commas or periods
                        (?:{sep}[A-Z0-9-#]{{1,7}}){{0,7}}
                        # end street address capturing group
                    )
                    {sep}
                    #  a non-capturing city name followed maybe by a comma -- up to four possibly longer words
                    (?:[A-Z0-9-]{{1,15}}{sep}){{1,4}}
                    # required capturing group: a state name or abbreviation [States49 DOES NOT INCLUDE MASSACHUSETTS]
                    (?<state>{States49.Terms}){sep}
                    # required capturing group 4 zip code (5 or 9, with or with out hyphen)
                    {zipCode}
                    ",
                   RegexOptions.IgnoreCase | RegexOptions.IgnorePatternWhitespace,
                   TimeSpan.FromSeconds(20)
                   );

            try
            {
                Match match = notMARegex.Match(message.OriginalMessage.uniqueBody.content);
                if (match.Success)
                {
                    message.state = match.Groups["state"].Value;
                    message.zip = match.Groups["zip"].Value;
                    message.AddressIndex = match.Index;
                    message.AddressLength = (short)match.Length;
                    return;
                }
            }
            catch (RegexMatchTimeoutException e)
            {
                // no need to handle exception -- just treat as nonmatch
                Console.Write("Regex timeout in Address Extraction, line 168" + e.ToString());
            }
      
             /*
             * Don't have a city/state MA address
             * Don't have a street/city/state/zip nonMA address
             * At this point will take a city as address . . . ?
             *   . . . no:  better not to.  Too many bogus results and can lead to bad name find which we want to avoid.
             */

            return;
        }

        private static string StripExtraWhiteSpace( string hasExtra )
        {
            return Regex.Replace(hasExtra, @"\s+", " ");
        }

        // does a candidate match address contain strings indicating that it is
        // an inside address for the to recipient legislator
        // returns true if disqualified
        private static bool CheckDisqualifiers(String testMatch)
        {
            try
                {
                    var dqRegex = new Regex(
                    $@"
                    (?:{parseMessages.AddressExtraction.Terms.Disqualifiers.Terms})
                    ",
                    RegexOptions.IgnoreCase | RegexOptions.IgnorePatternWhitespace,
                    TimeSpan.FromSeconds(2)
                );
                // if a 
                return dqRegex.IsMatch(testMatch);
            }
            catch
            {
                return true;
            }
        }
    }
}
