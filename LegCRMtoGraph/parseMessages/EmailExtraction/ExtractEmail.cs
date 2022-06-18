using System;
using System.Collections.Generic;
using System.Text;
using parseMessages.ParsedMessageObjects;
using parseMessages.NameExtraction;
using System.Text.RegularExpressions;
using parseMessages.PhoneExtraction;

namespace parseMessages.EmailExtraction
{
    class ExtractEmail
    {

        public static string emailRegex = @"\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b"; // https://www.regular-expressions.info/email.html

        public static void Extract(ref CRMParsedMessageObject message)
        {
            
            // use reply email if it exists; prioritize reply email
            if ( !String.IsNullOrEmpty(message.reply_to_email) )
            {
                message.email_address = message.reply_to_email;
                return;
            }

            // extract 

            // look in message in only around a found address for an email
            if (!String.IsNullOrEmpty(message.state))
            {
                int ubLength = message.OriginalMessage.uniqueBody.content.Length;

                // first search region -- immediately before address (with possible phone in between; next character is first character of address
                string searchRegion = message.OriginalMessage.uniqueBody.content[
                        Math.Max(message.AddressIndex - 200, 0)..message.AddressIndex]; 
                Match match = Regex.Match(searchRegion, $@"(?:^|\s)({emailRegex})\s+(?:{ExtractPhone.phoneRegex}\s+)?$", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(1));
                if ( match.Success && match.Groups[1].Value != message.to_email ) // don't mix up the from with the to
                {
                    message.email_address = match.Groups[1].Value;
                    return;
                }

                // secondsearch region -- immediately after address (with possible phone in between; previous character is last character of address
                searchRegion = message.OriginalMessage.uniqueBody.content[
                        (message.AddressIndex + message.AddressLength) ..Math.Min(message.AddressIndex + message.AddressLength + 200, ubLength)];
                match = Regex.Match(searchRegion, $@"^\s+(?:{ExtractPhone.phoneRegex}\s+)?({emailRegex})(?:\s|$)", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(1));
                if (match.Success && match.Groups[1].Value != message.to_email) // don't mix up the from with the to
                {
                    message.email_address = match.Groups[1].Value;
                    return;
                }

            }

            // otherwise use from email
            message.email_address = message.from_email;

            /*
             * this approach will mistakenly use an embedded email over the from email if a person (with no reply to, but valid from) . . . 
             *     . . . directly sent a unique body containing a parseable address and email for another person.
             */
        }
    }
}
