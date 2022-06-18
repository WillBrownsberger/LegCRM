using System;
using System.Collections.Generic;
using System.Text;

namespace parseMessages.NameExtraction.Terms
{
    public static class Titles 
    {
		// titles without trailing periods (insisting on word boundary in regex; . would be the word boundary and fail regex)
        public static string Terms = @"Mr|Ms|Miss|Mrs|constituent|Dr|Doctor|Atty|Jr|Sr|II|III|IV|Esq|A\.B|B\.A|B\.S|B\.E|B\.F\.A|B\.Tech|"
			+ @"L\.L\.B|B\.Sc|M\.A|M\.S|M\.F\.A|LL\.M|M\.L\.A|M\.B\.A|M\.Sc|M\.Eng|J\.D|M\.D|D\.O|"
			+ @"Pharm\.D|Ph\.D|Ed\.D|D\.Phil|LL\.D|Eng\.D|Dr|CA|CPA|C\.P\.A|Accountant|P\.E|AB|BA|BS|BE|BFA|"
			+ @"BTech|LLB|BSc|MA|MS|MFA|LLM|MLA|MBA|MSc|MEng|JD|MD|DO|Partner|PharmD|PhD|EdD|Governor|Judge|Father|Monsignor|Brother|Sister|Rabbi|Imam|Doctor"
			+ @"DPhil|LLD|EngD|Attorney|Lawyer|CA|PE|The Honorable|Honorable|Hon|State Senator|Sen|Senator|Rep|" 
			+ @"Representative|State Representative|Councilor|On behalf of|M|SEN|HOU|President|Executive Director|RN|R\.N";

	}
}
