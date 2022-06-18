using System;
using System.Collections.Generic;
using System.Text;
using parseMessages.ParsedMessageObjects;
using System.Text.RegularExpressions;
using parseMessages.AssignCategory.Terms;
using System.Linq;
using Microsoft.Graph;

namespace parseMessages.AssignCategory
{
    class Categorize
    {
        public static void CategorizeMessage(ref CRMParsedMessageObject message)
        {
            /* 
             * goal here is to identify ADVOCACY and BULK mailers
             * 
             * GOV is based on from address
             * 
             * INDIVIDUAL is the residual default
             * 
             */

            // if we are dealing with a trained message, it is advocacy on that basis alone
            if (message.Batch_mapped_issue > 0)
            {
                message.category = "CATEGORY_ADVOCACY";
                return;
            }

            // certain facilities use bulk mailing tools to transmit individual messages, like form submissions, skim these off 
            if (Regex.IsMatch(message.from_email, $@"\b(?:{IndividualOverrides.Terms})\b", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2)))
            {
                message.category = "CATEGORY_INDIVIDUAL";
                return;
            }

            string spamHeader = string.Empty;

            // identify bulk/advocacy emails by from or headers -- mailings to groups identifiable by to address; reply address may be individual, can't screen by that
            if (Regex.IsMatch(message.from_email + " " + message.to_email, $@"\b(?:{AdvocacyMailers.Terms})\b", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2)))
            {
                message.category = "CATEGORY_ADVOCACY";
                return;
            }
            else if (Regex.IsMatch(message.from_email + " " + message.to_email, $@"\b(?:{BulkSenders.Terms})\b", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2)))
            {
                message.category = "CATEGORY_BULK";
                return;
            }

            // some outlook system messages have no internet headers -- need to test for null value
            if (message.OriginalMessage.internetMessageHeaders != null ) {
                // if hit mailers, done and return, otw, extract spam header
                foreach (parseMessages.ParsedMessageObjects.InternetMessageHeader header in message.OriginalMessage.internetMessageHeaders)
                {
                    // signature may contain subscribe
                    if (header.name.ToLower() == "dkim-signature")
                    {
                        continue;
                    }

                    if (header.name.ToLower() == "x-proofpoint-spam-details")
                    {
                        spamHeader = header.value;
                    }
                    if (Regex.IsMatch(header.name + " " + header.value, $@"(:?{AdvocacyMailers.Terms})", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2)))
                    {
                        message.category = "CATEGORY_ADVOCACY";
                        return;
                    }
                    else if (Regex.IsMatch(header.name + " " + header.value, $@"(:?{BulkSenders.Terms})", RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2)))
                    {
                        message.category = "CATEGORY_BULK";
                        return;
                    }
                }


                if (!string.IsNullOrEmpty(spamHeader))
                {
                    bool spamFlag = false;
                    // parse spamHeader into Dictionary
                    string[] details = spamHeader.Split(" ");
                    Dictionary<string, string> spamDetails = new Dictionary<string, string>();
                    foreach (string detail in details)
                    {
                        string[] detailSplit = detail.Split("=");
                        spamDetails.Add(detailSplit[0], detailSplit[1]);
                    }
                    //apply details to identify soft spam
                    if (spamDetails.TryGetValue("bulkscore", out string bulkScore))
                    {
                        if (int.TryParse(bulkScore, out int i)) {
                            if (i > 10) spamFlag = true;
                        }
                    }
                    if (spamDetails.TryGetValue("suspectscore", out string suspectScore))
                    {
                        if (int.TryParse(suspectScore, out int j))
                        {
                            if (j > 10) spamFlag = true;
                        }
                    }
                    // apply spam finding
                    if (spamFlag)
                    {
                        if (message.Batch_found_constituent_id > 0)
                        {
                            message.category = "CATEGORY_ADVOCACY";
                            return;
                        } else
                        {
                            message.category = "CATEGORY_BULK";
                            return;
                        }
                    }
                }
            }

            // has not been classified as bulk or advocacy try classification as Government email
            if (Regex.IsMatch(message.from_email, @"\.gov|\.ma\.us")) {
                message.category = "CATEGORY_GOV";
                return;
            }


            // if no reason to classify as bulk or advocacy or gov, classify as individual
            message.category = "CATEGORY_INDIVIDUAL";
        }
        public static string[] ParseOutlookCategories(ParsedMessageObjects.CRMParsedMessageObject message)
        {
            var isMyConstituent = message.Batch_is_my_constituent;
            var category = message.category;
            List<string> cats = new List<string>();

            if ("CATEGORY_GOV" == category)
            {
                if (message.from_domain.ToLower() == "masenate.gov")
                {
                    cats.Add("Senate");

                } 
                else if (message.from_domain.ToLower() == "mahouse.gov")
                {
                    cats.Add("House");
                }
                else 
                {
                cats.Add("Gov");
                }
            }
            else if ("CATEGORY_BULK" == category)
            {
               cats.Add( "Bulk" );
            } 
            else if ("CATEGORY_ADVOCACY" == category )
            {
               cats.Add("Advocacy");
            }

            if ("Y" == isMyConstituent)
            {
                cats.Add("Constituent");
            }
            else if ("N" == isMyConstituent) 
            {
                cats.Add("OutOfDistrict");
            }

            return cats.ToArray();

        }
        public static InferenceClassificationType  ParseOutlookInferenceClassification(string[] categories)
        {
            string[] junkCategories = { "Bulk", "Advocacy", "OutOfDistrict" };
            var junk = categories.Intersect(junkCategories);
            return junk.Count() > 0 ? InferenceClassificationType.Other : InferenceClassificationType.Focused; 
        } 
    }
}
