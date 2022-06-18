using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.ParsedMessageObjects
{
    class UnparsedMessage
    {
        public short OFFICE { get; set; }
        public string OfficeEmail { get; set; }
        public long ID { get; set; }
        public string MessageID { get; set; }
        public string MessageJson { get; set; }

        public UnparsedMessage ( short InOffice, string InOfficeEmail, long InID, string InMessageID, string InMessageJson)
        {
            OFFICE = InOffice;
            OfficeEmail = InOfficeEmail;
            ID = InID;
            MessageID = InMessageID;
            MessageJson = InMessageJson;
        }
    }
}
