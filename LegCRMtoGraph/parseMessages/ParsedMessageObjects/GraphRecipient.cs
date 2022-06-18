using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.ParsedMessageObjects
{
    class GraphRecipient
    {
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public EmailAddress emailAddress { get; set; }
    }
}

/*
 * https://docs.microsoft.com/en-us/graph/api/resources/recipient?view=graph-rest-1.0
 * 
 * {
  "emailAddress": {"@odata.type": "microsoft.graph.emailAddress"}
}
*/
