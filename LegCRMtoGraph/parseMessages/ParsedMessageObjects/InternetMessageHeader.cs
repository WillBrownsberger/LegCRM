using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.ParsedMessageObjects
{
    class InternetMessageHeader
    {
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String name { get; set; }

        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String value { get; set; }
    }
}
/*
 * https://docs.microsoft.com/en-us/graph/api/resources/internetmessageheader?view=graph-rest-1.0
*
*{
  "name": "string",
  "value": "string"
}
*/