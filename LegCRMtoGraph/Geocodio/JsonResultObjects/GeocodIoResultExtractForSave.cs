using System;
using System.Collections.Generic;
using System.Text;

// object properties named for consistency with SQL JSON object definition
namespace GeocodIo.JsonResultObjects
{
	class GeocodIoResultExtractForSave
	{
        [System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
        public int cache_ID  {get; set;}
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
		public decimal lat { get; set; }
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
		public decimal lon { get; set; }
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
		public string zip_clean { get; set; }
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
		public string matched_clean_address { get; set; }
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
		public double geocode_vendor_accuracy { get; set; }
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
		public string geocode_vendor_accuracy_type { get; set; }
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<SQL JSON object consistency>")]
		public string geocode_vendor_source { get; set; }
	}
}
