using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.ParsedMessageObjects
{
    class EmailAddress
    {
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String address { get; set; }
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String name { get; set; }
    }
}

/*
 * https://docs.microsoft.com/en-us/graph/api/resources/emailaddress?view=graph-rest-1.0
{
  "address": "string",
  "name": "string"
}
*/
