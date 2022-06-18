using System;
using System.Collections.Generic;
using System.Text;
using parseMessages.ParsedMessageObjects;
using System.Text.RegularExpressions;
using parseMessages.EmailExtraction;

namespace parseMessages.PhoneExtraction
{
    class ExtractPhone
    {

        public static string phoneRegex = @"\(?\b[2-9][0-9]{2}\)?[-. ]?[2-9][0-9]{2}[-. ]?[0-9]{4}\b";
        private static string extractedPhone = string.Empty;

        // look for the first phone before or after the address (do in one string including the address)
        // only search in case of found address
        public static void Extract( ref CRMParsedMessageObject message)
        {
            // reset extracted phone!
            extractedPhone = string.Empty;

            // look in message only around a found address 
            if (!String.IsNullOrEmpty(message.state))
            {
                int ubLength = message.OriginalMessage.uniqueBody.content.Length;

                // first search region -- immediately before address (with possible email in between; next character is first character of address)
                string searchRegion = message.OriginalMessage.uniqueBody.content[
                        Math.Max(message.AddressIndex - 200, 0)..message.AddressIndex];
                Match match = Regex.Match(searchRegion, $@"(?:^|\s)({phoneRegex})\s+(:?{ExtractEmail.emailRegex}\s+)?$", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(1));
                if (match.Success)
                {
                    extractedPhone = match.Groups[1].Value;
                }
                else
                {
                    // if not found in first, attempt second search region -- immediately after address (with possible email in between; previous character is last character of address
                    searchRegion = message.OriginalMessage.uniqueBody.content[
                            (message.AddressIndex + message.AddressLength)..Math.Min(message.AddressIndex + message.AddressLength + 200, ubLength)];
                    match = Regex.Match(searchRegion, $@"^\s+(?:{ExtractEmail.emailRegex}\s+)?({phoneRegex})(?:\s|$)", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(1));
                    if (match.Success)
                    {
                        extractedPhone = match.Groups[1].Value;
                    }
                }

                if (extractedPhone != string.Empty)
                {
                    string phoneStringStripped = Regex.Replace(extractedPhone, @"[^\d]", string.Empty);
                    if (phoneStringStripped.Length == 10)
                    {
                        message.phone_number = long.Parse(phoneStringStripped);
                    } //

                }
            }
                





        }
    }
}
