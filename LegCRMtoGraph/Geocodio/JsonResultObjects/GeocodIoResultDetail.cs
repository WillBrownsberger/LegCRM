using System;
using System.Collections.Generic;
using System.Text;

namespace GeocodIo.JsonResultObjects
{
    class GeocodIoResultDetail
    {
        public GeoCodIoResultDetailItem Input { get; set; }
        public GeoCodIoResultDetailItem[] Results { get; set; }
    }
}
