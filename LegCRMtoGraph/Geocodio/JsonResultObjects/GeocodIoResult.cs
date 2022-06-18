using System;
using System.Collections.Generic;
using System.Text;

namespace GeocodIo.JsonResultObjects
{
    class GeocodIoResult
    {
        public string Query { get; set; }
        public GeocodIoResultDetail Response { get; set; }
    }
 
}
