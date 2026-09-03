//! Vocabulary for generated records.
//!
//! Deliberately Tanzanian and construction-flavoured: seeded data that reads
//! like the real thing is data you can spot a formatting bug in. Lorem ipsum
//! hides exactly the problems a scale test is meant to surface — a name that
//! overflows its column, a currency that renders wrong at four digits.

use rand::rngs::StdRng;
use rand::Rng;

pub const CITIES: &[&str] = &[
    "Dodoma", "Dar es Salaam", "Arusha", "Mwanza", "Mbeya", "Morogoro", "Tanga",
    "Iringa", "Moshi", "Singida", "Tabora", "Songea", "Musoma", "Shinyanga",
];

pub const LOCATIONS: &[&str] = &[
    "Main store", "Mkonze yard", "Site container A", "Site container B",
    "Head office", "Workshop", "Cold store", "Transit",
];

pub const DEPARTMENTS: &[(&str, &str)] = &[
    ("Administration", "ADM"),
    ("Finance", "FIN"),
    ("Procurement", "PRC"),
    ("Operations", "OPS"),
    ("Engineering", "ENG"),
    ("Information Technology", "IT"),
    ("Human Resources", "HR"),
    ("Community Programmes", "CMP"),
];

pub const EXPENSE_ACCOUNTS: &[&str] = &[
    "Fuel and lubricants", "Office supplies", "Travel and per diem",
    "Vehicle maintenance", "Utilities", "Telephone and internet",
    "Professional fees", "Site consumables", "Training", "Bank charges",
];

pub const JOURNAL_MEMOS: &[&str] = &[
    "Monthly accrual", "Bank reconciliation", "Depreciation charge",
    "Payroll posting", "Fuel reallocation", "Site cost transfer",
    "Provision adjustment", "Grant recognition", "Retention release",
];

pub const PROJECTS: &[&str] = &[
    "Water supply scheme", "Classroom block", "Health centre", "Market rehabilitation",
    "Bridge replacement", "Feeder road upgrade", "Staff housing", "Drainage improvement",
    "Borehole programme", "Solar installation", "Perimeter wall", "Office refurbishment",
];

pub const TICKET_SUBJECTS: &[&str] = &[
    "Invoice does not match delivery note", "Cannot access the portal",
    "Duplicate charge on statement", "Delivery has not arrived",
    "Request a copy of the receipt", "Change billing address",
    "Wrong item supplied", "Quotation needs revising",
    "Payment not reflected", "Certificate needed for audit",
];

pub const SERVICES: &[&str] = &[
    "Site inspection", "Materials testing", "Survey visit", "Design consultation",
    "Equipment servicing", "Training session", "Valuation visit", "Handover walkthrough",
];

pub const REQUESTS: &[&str] = &[
    "Cement for foundation works", "Replacement laptop", "Site safety equipment",
    "Fuel for generators", "Office stationery", "Vehicle service and tyres",
    "Survey instrument hire", "Steel reinforcement", "Water tanks", "Printing of drawings",
];

pub const RECURRING: &[&str] = &[
    "Monthly site supervision", "Equipment hire retainer", "Maintenance contract",
    "Quarterly inspection", "Annual software licence", "Cleaning services",
    "Security services", "Generator servicing",
];

const FIRST: &[&str] = &[
    "Asha", "Baraka", "Chausiku", "Daudi", "Esther", "Frank", "Grace", "Hamisi",
    "Imani", "Juma", "Kelvin", "Lucy", "Msafiri", "Neema", "Omary", "Pendo",
    "Rehema", "Salum", "Tumaini", "Upendo", "Victor", "Witness", "Yusuf", "Zawadi",
];

const LAST: &[&str] = &[
    "Mushi", "Kimaro", "Mwakyusa", "Shirima", "Massawe", "Ngowi", "Mbwana",
    "Kileo", "Lyimo", "Mrema", "Sanga", "Mgaya", "Kilasi", "Nyerere",
    "Makundi", "Chuwa", "Mollel", "Swai", "Temba", "Urio",
];

const COMPANY_HEAD: &[&str] = &[
    "Kilimanjaro", "Serengeti", "Ruaha", "Uhuru", "Bahari", "Nyati", "Simba",
    "Tembo", "Mlima", "Bonde", "Ziwa", "Pwani", "Mwanga", "Jengo",
];

const COMPANY_TAIL: &[&str] = &[
    "Contractors Ltd", "Enterprises", "Trading Co.", "Engineering Ltd",
    "Supplies Ltd", "Holdings", "Works Ltd", "Logistics", "Group Ltd",
];

const VENDOR_TAIL: &[&str] = &[
    "Hardware", "Suppliers", "General Supplies", "Builders Merchants",
    "Motors", "Steel Mart", "Cement Depot", "Fuel Services", "Electricals",
];

/// (name, unit, role, google_category)
const ITEMS: &[(&str, &str, &str, &str)] = &[
    ("Desktop computer", "unit", "asset", "328 - Electronics > Computers"),
    ("Laptop computer", "unit", "asset", "328 - Electronics > Computers"),
    ("Office printer", "unit", "asset", "328 - Electronics > Print, Copy, Scan"),
    ("Office desk", "unit", "asset", "436 - Furniture > Desks"),
    ("Office chair", "unit", "asset", "436 - Furniture > Chairs"),
    ("Portland cement 50kg", "bag", "material", "632 - Hardware > Building Materials"),
    ("Reinforcement steel 12mm", "length", "material", "632 - Hardware > Building Materials"),
    ("Crushed stone 20mm", "tonne", "material", "632 - Hardware > Building Materials"),
    ("River sand", "tonne", "material", "632 - Hardware > Building Materials"),
    ("Bitumen 60/70", "drum", "material", "632 - Hardware > Building Materials"),
    ("Diesel fuel", "litre", "material", "632 - Hardware > Fuel"),
    ("Safety helmet", "unit", "product", "2047 - Apparel > Protective Gear"),
    ("Safety boots", "pair", "product", "2047 - Apparel > Protective Gear"),
    ("Hi-vis vest", "unit", "product", "2047 - Apparel > Protective Gear"),
    ("Survey tripod", "unit", "asset", "632 - Hardware > Tools"),
    ("Concrete mixer", "unit", "asset", "632 - Hardware > Tools"),
    ("Water pump", "unit", "asset", "632 - Hardware > Tools"),
    ("Generator 15kVA", "unit", "asset", "632 - Hardware > Tools"),
    ("Site supervision", "day", "service", ""),
    ("Materials testing", "test", "service", ""),
    ("Topographic survey", "day", "service", ""),
    ("Equipment hire", "day", "service", ""),
    ("Haulage", "trip", "service", ""),
    ("Borehole drilling", "metre", "service", ""),
];

pub fn person(rng: &mut StdRng, i: usize) -> (String, String) {
    let f = FIRST[(i + rng.gen_range(0..FIRST.len())) % FIRST.len()];
    let l = LAST[(i + rng.gen_range(0..LAST.len())) % LAST.len()];
    (f.to_string(), l.to_string())
}

pub fn company(rng: &mut StdRng, i: usize) -> String {
    let h = COMPANY_HEAD[(i + rng.gen_range(0..COMPANY_HEAD.len())) % COMPANY_HEAD.len()];
    let t = COMPANY_TAIL[(i + rng.gen_range(0..COMPANY_TAIL.len())) % COMPANY_TAIL.len()];
    format!("{h} {t}")
}

pub fn vendor(rng: &mut StdRng, i: usize) -> String {
    let h = COMPANY_HEAD[(i + rng.gen_range(0..COMPANY_HEAD.len())) % COMPANY_HEAD.len()];
    let t = VENDOR_TAIL[(i + rng.gen_range(0..VENDOR_TAIL.len())) % VENDOR_TAIL.len()];
    format!("{h} {t}")
}

/// A catalogue entry. Beyond the fixed list the names take a size suffix, so a
/// large run keeps producing distinguishable items rather than the same
/// twenty-four names repeated a thousand times.
pub fn item(rng: &mut StdRng, i: usize) -> (String, &'static str, &'static str, &'static str) {
    let (name, unit, role, category) = ITEMS[i % ITEMS.len()];
    let round = i / ITEMS.len();

    let full = if round == 0 {
        name.to_string()
    } else {
        let grade = ["Grade A", "Grade B", "Heavy duty", "Standard", "Type II", "Mk III"];
        format!("{name} ({})", grade[(round + rng.gen_range(0..2)) % grade.len()])
    };

    (full, unit, role, category)
}

/// The asset category an item belongs to, from what the item actually is.
///
/// Picking the category independently of the item produced records like a drum
/// of bitumen filed as a computer — data that is present but not believable,
/// which is worse than none for spotting real bugs.
pub fn asset_category(item_name: &str) -> &'static str {
    let n = item_name.to_ascii_lowercase();

    if n.contains("computer") || n.contains("laptop") || n.contains("printer") {
        "computer"
    } else if n.contains("desk") || n.contains("chair") {
        "furniture"
    } else if n.contains("mixer") || n.contains("pump") || n.contains("generator") || n.contains("tripod") {
        "equipment"
    } else if n.contains("helmet") || n.contains("boots") || n.contains("vest") {
        "tool"
    } else {
        "other"
    }
}

/// Items worth capitalising. Bitumen and sand are consumed, not owned, so an
/// asset register full of them is a register nobody would trust.
pub fn is_capitalisable(item_name: &str) -> bool {
    asset_category(item_name) != "other"
}

/// Lowercase, alphanumeric-and-dashes — safe inside a generated email address.
pub fn slug(s: &str) -> String {
    let mut out = String::with_capacity(s.len());
    let mut dash = false;

    for c in s.chars() {
        if c.is_ascii_alphanumeric() {
            out.push(c.to_ascii_lowercase());
            dash = false;
        } else if !dash && !out.is_empty() {
            out.push('-');
            dash = true;
        }
    }

    out.trim_end_matches('-').to_string()
}
