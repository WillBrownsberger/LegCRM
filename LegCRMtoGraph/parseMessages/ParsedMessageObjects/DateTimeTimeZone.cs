using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.ParsedMessageObjects
{
    class DateTimeTimeZone
    {
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String dateTime { get; set; }

        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String timeZone { get; set; }

        /*
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String @odata.type { get; set; } */
    }
}

/*
 * https://docs.microsoft.com/en-us/graph/api/resources/datetimetimezone?view=graph-rest-1.0
 * 
 * 
 {
  "dateTime": "string",
  "timeZone": "string"
}
*/
 
