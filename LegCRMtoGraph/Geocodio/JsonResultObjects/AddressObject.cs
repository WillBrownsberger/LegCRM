using System;
using System.Collections.Generic;
using System.Text;

namespace GeocodIo.JsonResultObjects
{
    class AddressObject
    {
        public string Number { get; set; }
        public string Predirectional { get; set; }
        public string Street { get; set; }
        public string Suffix { get; set; }
        public string Formatted_Street { get; set; }
        public string City { get; set; }
        public string County { get; set; }
        public string State { get; set; }
        public string Zip { get; set; }
        public string Country { get; set; }
    }
}
