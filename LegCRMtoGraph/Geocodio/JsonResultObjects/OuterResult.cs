using System;
using System.Collections.Generic;
using System.Text;
namespace GeocodIo.JsonResultObjects
// credit: not copying, but using https://github.com/snake-plissken/cSharpGeocodio as guide for layout of json backing classes
{
    class OuterResult
    {
        public GeocodIo.JsonResultObjects.GeocodIoResult[] Results { get; set; }
    }
}
