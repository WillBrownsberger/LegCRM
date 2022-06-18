using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.ParsedMessageObjects
{
    class ItemBody
    {
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String content { get; set; }
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String contentType { get; set; }
    }
}
/*
 * https://docs.microsoft.com/en-us/graph/api/resources/itembody?view=graph-rest-1.0
 * 
 * {
  "content": "string",
  "contentType": "String"
}
*/
