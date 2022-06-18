using System;
using System.Collections.Generic;
// note: on php side, apply WIC_Entity_Email_Send::standardize_json_message for compatibility with this.
namespace SendMailGraph
{
    public class CRMMessageObject

    {
		public CRMRecipient[] To_array { get; set; }
		public CRMRecipient[] Cc_array { get; set; }
        public CRMRecipient[] Bcc_array { get; set; }
		public string Subject { get; set; }
		public string Html_body { get; set; }

		// additional properties included in object, but not used in sending 
		public string Text_body { get; set; }
		public int Is_draft { get; set; }
		public int Is_reply_to { get; set; }
		public bool Include_attachments { get; set; } // irrelevant -- governs upstream linkage of attachments in forwarded messages
		public int Issue { get; set; }
		public string Pro_con { get; set; }
		public string Search_type { get; set; }
		public int Search_id { get; set; }
		public string Search_parm { get; set; }
		public int Draft_id { get; set; }

    }

	public class CRMRecipient
    {
		public string Name { get; set; }
		public string Address { get; set; }
		public int Constituent { get; set; }
	}
}
