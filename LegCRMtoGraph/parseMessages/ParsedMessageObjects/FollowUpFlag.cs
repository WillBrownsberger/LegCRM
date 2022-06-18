using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.ParsedMessageObjects
{
    class FollowUpFlag
    {
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public DateTimeTimeZone completedDate { get; set; }
        
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public DateTimeTimeZone dueDateTime { get; set; }
        
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public String flagStatus { get; set; }

        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
        public DateTimeTimeZone startDateTime { get; set; }
    }
}
/*
 * https://docs.microsoft.com/en-us/graph/api/resources/followupflag?view=graph-rest-1.0
 * 
{
  "completedDateTime": {"@odata.type": "microsoft.graph.dateTimeTimeZone"},
  "dueDateTime": {"@odata.type": "microsoft.graph.dateTimeTimeZone"},
  "flagStatus": "String",
  "startDateTime": {"@odata.type": "microsoft.graph.dateTimeTimeZone"}
}
*/
