using System;
using System.Collections.Generic;
using System.Text;

namespace GeocodIo.JsonResultObjects
{
    class GeoCodIoResultDetailItem
    {
        public AddressObject Address_Components { get; set; }
        public string Formatted_Address { get; set; }
        public Location Location { get; set; }
        public double Accuracy { get; set; }
        public string Accuracy_Type { get; set; }
        public string Source { get; set; }
    }
}
