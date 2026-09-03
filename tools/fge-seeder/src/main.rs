//! Bulk data seeder for the FGE admin.
//!
//! Writes straight into the Laravel SQLite file. Seeding at this volume through
//! Eloquent would mean one model instantiation per row; here a hundred thousand
//! rows is a handful of prepared statements inside one transaction.
//!
//! **It never touches `content_sections`, `websites`, `users` or
//! `organization_members`.** The site's content and its people are real —
//! imported from the legacy system — and a seeder that overwrote them would be
//! destroying records, not generating them. Everything it writes is business
//! data it can also delete again, keyed to one organization.

use chrono::{Duration, NaiveDate};
use clap::Parser;
use rand::rngs::StdRng;
use rand::{Rng, SeedableRng};
use rusqlite::{params, Connection, Transaction};
use uuid::Uuid;

mod names;

#[derive(Parser)]
#[command(name = "fge-seeder", about = "Generate FGE business data at volume.")]
struct Args {
    /// Path to the Laravel SQLite database.
    #[arg(long, default_value = "../../database/database.sqlite")]
    db: String,

    /// Organization slug to seed into.
    #[arg(long, default_value = "fge")]
    org: String,

    /// Roughly how many rows to generate in total, across every table.
    #[arg(long, default_value_t = 100_000)]
    rows: usize,

    /// Remove previously seeded business data for this organization first.
    #[arg(long)]
    fresh: bool,

    /// Seed for the generator, so a run is reproducible.
    #[arg(long, default_value_t = 20260813)]
    seed: u64,
}

/// How the total row budget is split. The shape matters more than the size: a
/// business has far more document lines than customers, and a seeder that
/// ignored that would produce a database nothing realistic can be tested on.
struct Plan {
    vendors: usize,
    departments: usize,
    customers: usize,
    items: usize,
    stock: usize,
    assets: usize,
    documents: usize,
    lines_per_doc: usize,
    payments: usize,
    expenses: usize,
    journal_entries: usize,
    // The operational modules. Smaller than the financial ones because a
    // business raises far more invoice lines than it does purchase requests,
    // but every module must have something in it — an empty table teaches
    // nothing about whether its screens work.
    projects: usize,
    tickets: usize,
    orders: usize,
    bookings: usize,
    subscriptions: usize,
    purchase_orders: usize,
    purchase_requests: usize,
    shipments: usize,
    recurring: usize,
}

impl Plan {
    fn for_rows(total: usize) -> Self {
        let t = total as f64;
        Plan {
            vendors: ((t * 0.004) as usize).max(8),
            departments: 8,
            customers: ((t * 0.03) as usize).max(20),
            items: ((t * 0.02) as usize).max(30),
            stock: ((t * 0.05) as usize).max(40),
            assets: ((t * 0.04) as usize).max(30),
            documents: ((t * 0.12) as usize).max(50),
            lines_per_doc: 3,
            payments: ((t * 0.08) as usize).max(30),
            expenses: ((t * 0.06) as usize).max(30),
            journal_entries: ((t * 0.05) as usize).max(30),
            projects: ((t * 0.004) as usize).max(12),
            tickets: ((t * 0.02) as usize).max(25),
            orders: ((t * 0.02) as usize).max(25),
            bookings: ((t * 0.015) as usize).max(20),
            subscriptions: ((t * 0.003) as usize).max(10),
            purchase_orders: ((t * 0.012) as usize).max(20),
            purchase_requests: ((t * 0.012) as usize).max(20),
            shipments: ((t * 0.015) as usize).max(20),
            recurring: ((t * 0.003) as usize).max(10),
        }
    }

    fn total(&self) -> usize {
        self.vendors
            + self.departments
            + self.customers
            + self.items
            + self.stock
            + self.assets
            + self.documents * (1 + self.lines_per_doc)
            + self.payments
            + self.expenses
            + self.journal_entries * 3
            + self.projects
            + self.tickets
            + self.orders
            + self.bookings
            + self.subscriptions
            + self.purchase_orders
            + self.purchase_requests
            + self.shipments
            + self.recurring
    }
}

fn uuid() -> String {
    Uuid::new_v4().to_string()
}

/// Timestamps are written in UTC because that is what the application stores.
///
/// Writing local time here put every seeded record three hours into the future,
/// and the lists dutifully reported them as created "2 hours from now" — a
/// timezone bug that looks like a clock bug until you check what the column
/// actually holds.
fn now() -> String {
    chrono::Utc::now().format("%Y-%m-%d %H:%M:%S").to_string()
}

/// A date within the last `span` days, as SQLite's text date.
fn date_within(rng: &mut StdRng, span: i64) -> String {
    let base = chrono::Local::now().date_naive();
    let d: i64 = rng.gen_range(0..span);
    (base - Duration::days(d)).format("%Y-%m-%d").to_string()
}

fn add_days(date: &str, days: i64) -> String {
    NaiveDate::parse_from_str(date, "%Y-%m-%d")
        .map(|d| (d + Duration::days(days)).format("%Y-%m-%d").to_string())
        .unwrap_or_else(|_| date.to_string())
}

fn pick<'a, T>(rng: &mut StdRng, xs: &'a [T]) -> &'a T {
    &xs[rng.gen_range(0..xs.len())]
}

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let args = Args::parse();
    let plan = Plan::for_rows(args.rows);

    let mut conn = Connection::open(&args.db)?;

    // The pragmas are the difference between minutes and seconds on a bulk load.
    conn.execute_batch(
        "PRAGMA journal_mode = WAL;
         PRAGMA synchronous = OFF;
         PRAGMA temp_store = MEMORY;
         PRAGMA cache_size = -64000;",
    )?;

    let (org_id, currency): (String, String) = conn.query_row(
        "SELECT id, COALESCE(currency,'TZS') FROM organizations WHERE slug = ?1",
        params![args.org],
        |r| Ok((r.get(0)?, r.get(1)?)),
    )?;

    println!("organization {} ({})", args.org, org_id);
    println!("planning ~{} rows", plan.total());

    let mut rng = StdRng::seed_from_u64(args.seed);
    let started = std::time::Instant::now();

    let tx = conn.transaction()?;

    if args.fresh {
        wipe(&tx, &org_id)?;
    }

    let vendors = seed_vendors(&tx, &mut rng, &org_id, plan.vendors)?;
    let departments = seed_departments(&tx, &org_id, plan.departments)?;
    let customers = seed_customers(&tx, &mut rng, &org_id, &currency, plan.customers)?;
    let items = seed_items(&tx, &mut rng, &org_id, &vendors, plan.items)?;
    seed_stock(&tx, &mut rng, &org_id, &items, plan.stock)?;
    seed_assets(&tx, &mut rng, &org_id, &items, &departments, &vendors, plan.assets)?;
    let docs = seed_documents(
        &tx, &mut rng, &org_id, &currency, &customers, &items, plan.documents, plan.lines_per_doc,
    )?;
    seed_payments(&tx, &mut rng, &org_id, &docs, plan.payments)?;
    seed_expenses(&tx, &mut rng, &org_id, &currency, &vendors, plan.expenses)?;
    seed_journal(&tx, &mut rng, &org_id, &currency, plan.journal_entries)?;
    seed_operations(
        &tx, &mut rng, &org_id, &currency, &plan, &customers, &vendors, &departments, &docs,
    )?;

    tx.commit()?;

    // Rebuild the planner's statistics; without this SQLite keeps using the
    // plans it chose when every table was empty.
    conn.execute_batch("ANALYZE;")?;

    println!("done in {:.1}s", started.elapsed().as_secs_f64());
    report(&conn, &org_id)?;

    Ok(())
}

/// Remove what a previous run wrote, and nothing else.
fn wipe(tx: &Transaction, org: &str) -> rusqlite::Result<()> {
    println!("clearing previous business data…");

    // Children first, and only rows reachable from this organization.
    tx.execute(
        "DELETE FROM invoicing_lines WHERE document_id IN
           (SELECT id FROM invoicing_documents WHERE organization_id = ?1)",
        params![org],
    )?;
    tx.execute(
        "DELETE FROM accounting_journal_lines WHERE entry_id IN
           (SELECT id FROM accounting_journal_entries WHERE organization_id = ?1)",
        params![org],
    )?;

    for table in [
        "invoicing_payments",
        "invoicing_documents",
        "accounting_journal_entries",
        "inventory_stock",
        "assets_records",
        "invoicing_items",
        "expenses_records",
        "crm_customers",
        "procurement_vendors",
        "org_departments",
        // The operational modules.
        "projects_records",
        "support_tickets",
        "cart_orders",
        "bookings_appointments",
        "billing_subscriptions",
        "purchasing_orders",
        "procurement_requests",
        "fulfillment_shipments",
        "invoicing_recurring",
    ] {
        tx.execute(
            &format!("DELETE FROM {table} WHERE organization_id = ?1"),
            params![org],
        )?;
    }

    Ok(())
}

fn seed_vendors(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    n: usize,
) -> rusqlite::Result<Vec<String>> {
    let cats = ["goods", "services", "works", "consultancy", "transport"];
    let mut stmt = tx.prepare(
        "INSERT INTO procurement_vendors
         (id, organization_id, name, code, email, phone, address, tin, category,
          lead_time_days, payment_terms, active, notes, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,1,NULL,?12,?12)",
    )?;

    let mut ids = Vec::with_capacity(n);

    for i in 0..n {
        let id = uuid();
        let name = names::vendor(rng, i);
        stmt.execute(params![
            id,
            org,
            name,
            format!("SUP-{:04}", i + 1),
            format!("supply{}@{}.co.tz", i + 1, names::slug(&name)),
            format!("+255 7{:02} {:03} {:03}", rng.gen_range(10..99), rng.gen_range(100..999), rng.gen_range(100..999)),
            format!("{}, Tanzania", pick(rng, names::CITIES)),
            format!("{:09}", rng.gen_range(100000000u64..999999999)),
            pick(rng, &cats),
            rng.gen_range(1..30),
            pick(rng, &["30 days", "45 days", "60 days", "On delivery"]),
            now(),
        ])?;
        ids.push(id);
    }

    println!("  vendors      {n}");
    Ok(ids)
}

fn seed_departments(tx: &Transaction, org: &str, n: usize) -> rusqlite::Result<Vec<String>> {
    let mut stmt = tx.prepare(
        "INSERT INTO org_departments
         (id, organization_id, name, code, head, cost_centre, budget_minor, active, notes, created_at, updated_at)
         VALUES (?1,?2,?3,?4,NULL,?5,?6,1,NULL,?7,?7)",
    )?;

    let mut ids = Vec::new();

    for (i, (name, code)) in names::DEPARTMENTS.iter().take(n).enumerate() {
        let id = uuid();
        stmt.execute(params![
            id,
            org,
            name,
            code,
            format!("CC-{:03}", i + 1),
            (20_000_000i64 + (i as i64 * 5_000_000)) * 100,
            now(),
        ])?;
        ids.push(id);
    }

    println!("  departments  {}", ids.len());
    Ok(ids)
}

fn seed_customers(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    currency: &str,
    n: usize,
) -> rusqlite::Result<Vec<String>> {
    let mut stmt = tx.prepare(
        "INSERT INTO crm_customers
         (id, organization_id, contact_type, display_name, company_name, first_name, last_name,
          email, phone, currency, payment_terms, billing_city, billing_country, active, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,?12,'TZ',?13,?14,?14)",
    )?;

    let mut ids = Vec::with_capacity(n);

    for i in 0..n {
        let id = uuid();
        let company = rng.gen_bool(0.6);
        let (first, last) = names::person(rng, i);
        let display = if company {
            names::company(rng, i)
        } else {
            format!("{first} {last}")
        };

        // `contact_type` is which side of the ledger this contact sits on —
        // customer or vendor — not whether they are a business. Writing
        // "company" here made every CRM count read zero, because nothing
        // matched the two values the application actually filters on; being a
        // company is already expressed by `company_name` being set.
        let contact_type = if rng.gen_bool(0.85) { "customer" } else { "vendor" };

        stmt.execute(params![
            id,
            org,
            contact_type,
            display,
            if company { Some(display.clone()) } else { None::<String> },
            first,
            last,
            format!("{}@{}.co.tz", names::slug(&first), names::slug(&display)),
            format!("+255 7{:02} {:03} {:03}", rng.gen_range(10..99), rng.gen_range(100..999), rng.gen_range(100..999)),
            currency,
            pick(rng, &["Due on receipt", "15 days", "30 days", "45 days"]),
            pick(rng, names::CITIES),
            // A few dormant accounts, so "inactive" is a number worth showing.
            rng.gen_bool(0.92) as i32,
            now(),
        ])?;
        ids.push(id);
    }

    println!("  customers    {n}");
    Ok(ids)
}

/// An item, its selling rate and what it costs to buy. Returned with the rate so
/// documents can be priced without reading them back.
struct SeedItem {
    id: String,
    name: String,
    sku: String,
    rate_minor: i64,
    tracked: bool,
}

fn seed_items(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    vendors: &[String],
    n: usize,
) -> rusqlite::Result<Vec<SeedItem>> {
    let mut stmt = tx.prepare(
        "INSERT INTO invoicing_items
         (id, organization_id, name, sku, description, item_type, unit, rate_minor,
          purchase_rate_minor, tax_percent, track_inventory, stock_on_hand, active,
          google_category, vendor_id, role, reorder_level, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,0,1,?12,?13,?14,?15,?16,?16)",
    )?;

    let mut out = Vec::with_capacity(n);

    for i in 0..n {
        let id = uuid();
        let (name, unit, role, category) = names::item(rng, i);
        let goods = role != "service";
        // Cost first, then a margin — the other way round produces items that
        // sell below cost, which reads as a data-entry bug in the reports.
        let cost: i64 = rng.gen_range(5_000..2_500_000) * 100;
        let rate = (cost as f64 * rng.gen_range(1.15..1.85)) as i64;
        let sku = format!("SKU-{:05}", i + 1);

        stmt.execute(params![
            id,
            org,
            name,
            sku,
            format!("{name} — supplied for FGE operations."),
            if goods { "goods" } else { "service" },
            unit,
            rate,
            cost,
            18.0f64,
            goods as i32,
            category,
            if vendors.is_empty() { None } else { Some(pick(rng, vendors).clone()) },
            role,
            if goods { rng.gen_range(5..40) } else { 0 },
            now(),
        ])?;

        out.push(SeedItem { id, name: name.to_string(), sku, rate_minor: rate, tracked: goods });
    }

    println!("  items        {n}");
    Ok(out)
}

fn seed_stock(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    items: &[SeedItem],
    n: usize,
) -> rusqlite::Result<()> {
    let tracked: Vec<&SeedItem> = items.iter().filter(|i| i.tracked).collect();

    if tracked.is_empty() {
        return Ok(());
    }

    let mut stmt = tx.prepare(
        "INSERT INTO inventory_stock
         (id, organization_id, item_id, item_name, sku, location, quantity, reorder_level,
          unit_cost_minor, batch, expires_on, notes, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,NULL,NULL,?11,?11)",
    )?;

    for i in 0..n {
        let item = tracked[i % tracked.len()];
        stmt.execute(params![
            uuid(),
            org,
            item.id,
            item.name,
            item.sku,
            pick(rng, names::LOCATIONS),
            rng.gen_range(0..500) as f64,
            rng.gen_range(5..40) as f64,
            rng.gen_range(5_000..2_000_000) * 100,
            format!("B{:05}", rng.gen_range(1..99999)),
            now(),
        ])?;
    }

    println!("  stock        {n}");
    Ok(())
}

fn seed_assets(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    items: &[SeedItem],
    departments: &[String],
    vendors: &[String],
    n: usize,
) -> rusqlite::Result<()> {
    let statuses = ["in_use", "in_store", "under_repair", "retired"];
    let conditions = ["new", "good", "fair", "poor"];

    // Only things worth owning become assets. Consumables belong in stock.
    let ownable: Vec<&SeedItem> = items
        .iter()
        .filter(|i| names::is_capitalisable(&i.name))
        .collect();

    // Real seats to issue equipment to, so "assigned to" is a person on the
    // team rather than a blank column.
    let seats: Vec<i64> = {
        let mut stmt = tx.prepare(
            "SELECT id FROM organization_members WHERE organization_id = ?1 AND active = 1",
        )?;
        let rows = stmt
            .query_map(params![org], |r| r.get(0))?
            .collect::<rusqlite::Result<Vec<i64>>>()?;
        rows
    };

    let mut stmt = tx.prepare(
        "INSERT INTO assets_records
         (id, organization_id, tag, name, category, serial_number, item_id, assigned_to,
          department, location, status, condition, purchased_on, purchase_cost_minor,
          current_value_minor, useful_life_years, warranty_until, notes,
          department_id, assigned_user_id, vendor_id, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,NULL,NULL,?8,?9,?10,?11,?12,?13,?14,?15,NULL,?16,?17,?18,?19,?19)",
    )?;

    if ownable.is_empty() {
        println!("  assets       0 (no capitalisable items)");
        return Ok(());
    }

    for i in 0..n {
        let item = ownable[i % ownable.len()];
        let cost: i64 = rng.gen_range(150_000..12_000_000) * 100;
        let life: i64 = rng.gen_range(3..12);
        let bought = date_within(rng, 365 * 6);
        // Straight-line, so the seeded book values are internally consistent.
        let age_years = rng.gen_range(0.0..life as f64);
        let current = (cost as f64 * (1.0 - age_years / life as f64)).max(0.0) as i64;
        let status = *pick(rng, &statuses);

        // Only what is in use is held by somebody; a retired asset in the store
        // assigned to a named employee is a contradiction.
        let holder = if status == "in_use" && !seats.is_empty() {
            Some(*pick(rng, &seats))
        } else {
            None
        };

        stmt.execute(params![
            uuid(),
            org,
            format!("FGE-{:06}", i + 1),
            item.name.clone(),
            names::asset_category(&item.name),
            format!("SN{:010}", rng.gen_range(1u64..9_999_999_999)),
            item.id.clone(),
            pick(rng, names::LOCATIONS),
            status,
            pick(rng, &conditions),
            bought,
            cost,
            current,
            life,
            add_days(&bought, 365 * 2),
            if departments.is_empty() { None } else { Some(pick(rng, departments).clone()) },
            holder,
            if vendors.is_empty() { None } else { Some(pick(rng, vendors).clone()) },
            now(),
        ])?;
    }

    println!("  assets       {n}");
    Ok(())
}

struct SeedDoc {
    id: String,
    customer_id: String,
    total_minor: i64,
    issue_date: String,
}

#[allow(clippy::too_many_arguments)]
fn seed_documents(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    currency: &str,
    customers: &[String],
    items: &[SeedItem],
    n: usize,
    lines_per: usize,
) -> rusqlite::Result<Vec<SeedDoc>> {
    if customers.is_empty() || items.is_empty() {
        return Ok(vec![]);
    }

    let types = ["invoice", "invoice", "invoice", "estimate", "sales_receipt", "credit_note", "purchase_order", "bill"];
    let prefixes = |t: &str| match t {
        "estimate" => "QUO",
        "sales_receipt" => "REC",
        "credit_note" => "CN",
        "purchase_order" => "PO",
        "bill" => "BILL",
        _ => "INV",
    };

    let mut doc_stmt = tx.prepare(
        "INSERT INTO invoicing_documents
         (id, organization_id, customer_id, doc_type, number, status, issue_date, due_date,
          currency, subtotal_minor, tax_minor, discount_minor, total_minor, paid_minor,
          reference, notes, terms, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,0,?12,?13,NULL,NULL,NULL,?14,?14)",
    )?;

    // `invoicing_lines.id` is an auto-increment integer, not a uuid like its
    // parent — so the column is left out and SQLite assigns it.
    let mut line_stmt = tx.prepare(
        "INSERT INTO invoicing_lines
         (document_id, item_id, name, description, quantity, rate_minor, tax_percent,
          amount_minor, position, created_at, updated_at)
         VALUES (?1,?2,?3,NULL,?4,?5,?6,?7,?8,?9,?9)",
    )?;

    let mut out = Vec::with_capacity(n);
    let mut counters = std::collections::HashMap::<&str, usize>::new();

    for _ in 0..n {
        let doc_type = *pick(rng, &types);
        let counter = counters.entry(doc_type).or_insert(0);
        *counter += 1;

        let id = uuid();
        let customer = pick(rng, customers).clone();
        let issue = date_within(rng, 365 * 2);

        // Build the lines first so the header carries their true total; a
        // header total that disagrees with its lines is the classic seeded-data
        // bug that makes every downstream report untrustworthy.
        let mut subtotal = 0i64;
        let mut tax = 0i64;
        let count = rng.gen_range(1..=lines_per.max(1));
        let mut built = Vec::with_capacity(count);

        for position in 0..count {
            let item = &items[rng.gen_range(0..items.len())];
            let qty = rng.gen_range(1..25) as f64;
            let amount = (qty * item.rate_minor as f64) as i64;
            let line_tax = (amount as f64 * 0.18) as i64;
            subtotal += amount;
            tax += line_tax;
            built.push((item.id.clone(), item.name.clone(), qty, item.rate_minor, amount, position));
        }

        let total = subtotal + tax;

        let (status, paid) = match doc_type {
            "sales_receipt" => ("paid", total),
            "estimate" => ("sent", 0),
            _ => match rng.gen_range(0..10) {
                0..=3 => ("paid", total),
                4..=5 => ("partially_paid", total / 2),
                6..=7 => ("sent", 0),
                8 => ("overdue", 0),
                _ => ("draft", 0),
            },
        };

        doc_stmt.execute(params![
            id,
            org,
            customer,
            doc_type,
            format!("{}-{:06}", prefixes(doc_type), counter),
            status,
            issue,
            add_days(&issue, 30),
            currency,
            subtotal,
            tax,
            total,
            paid,
            now(),
        ])?;

        for (item_id, name, qty, rate, amount, position) in built {
            line_stmt.execute(params![
                id, item_id, name, qty, rate, 18.0f64, amount, position as i64, now(),
            ])?;
        }

        out.push(SeedDoc { id, customer_id: customer, total_minor: total, issue_date: issue });
    }

    println!("  documents    {} (+ lines)", out.len());
    Ok(out)
}

fn seed_payments(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    docs: &[SeedDoc],
    n: usize,
) -> rusqlite::Result<()> {
    if docs.is_empty() {
        return Ok(());
    }

    let methods = ["cash", "bank_transfer", "mobile_money", "cheque", "card"];
    let mut stmt = tx.prepare(
        "INSERT INTO invoicing_payments
         (id, organization_id, document_id, customer_id, amount_minor, paid_on, method,
          reference, notes, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,NULL,?9,?9)",
    )?;

    for i in 0..n {
        let doc = &docs[i % docs.len()];
        stmt.execute(params![
            uuid(),
            org,
            doc.id,
            doc.customer_id,
            (doc.total_minor / rng.gen_range(1..=3)).max(1),
            add_days(&doc.issue_date, rng.gen_range(0..40)),
            pick(rng, &methods),
            format!("RCPT-{:06}", i + 1),
            now(),
        ])?;
    }

    println!("  payments     {n}");
    Ok(())
}

fn seed_expenses(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    currency: &str,
    _vendors: &[String],
    n: usize,
) -> rusqlite::Result<()> {
    let statuses = ["draft", "submitted", "approved", "paid", "rejected"];
    let methods = ["cash", "bank_transfer", "mobile_money", "card"];

    let mut stmt = tx.prepare(
        "INSERT INTO expenses_records
         (id, organization_id, reference, account, vendor, amount_minor, currency, spent_on,
          status, payment_method, notes, receipt_url, billable, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,NULL,NULL,?11,?12,?12)",
    )?;

    for i in 0..n {
        stmt.execute(params![
            uuid(),
            org,
            format!("EXP-{:06}", i + 1),
            pick(rng, names::EXPENSE_ACCOUNTS),
            names::vendor(rng, i),
            rng.gen_range(10_000..3_000_000) * 100,
            currency,
            date_within(rng, 365 * 2),
            pick(rng, &statuses),
            pick(rng, &methods),
            rng.gen_bool(0.3) as i32,
            now(),
        ])?;
    }

    println!("  expenses     {n}");
    Ok(())
}

/// Balanced journal entries against the organization's existing accounts.
///
/// Every entry is a two-line debit/credit pair of the same amount, so the trial
/// balance the accounting module derives is in balance by construction.
fn seed_journal(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    _currency: &str,
    n: usize,
) -> rusqlite::Result<()> {
    let accounts: Vec<(String, String)> = {
        let mut stmt = tx.prepare(
            "SELECT id, account_type FROM accounting_accounts WHERE organization_id = ?1",
        )?;
        let rows = stmt
            .query_map(params![org], |r| Ok((r.get(0)?, r.get(1)?)))?
            .collect::<rusqlite::Result<Vec<_>>>()?;
        rows
    };

    let debit_side: Vec<&(String, String)> = accounts
        .iter()
        .filter(|(_, t)| t == "asset" || t == "expense")
        .collect();
    let credit_side: Vec<&(String, String)> = accounts
        .iter()
        .filter(|(_, t)| t == "income" || t == "liability" || t == "equity")
        .collect();

    if debit_side.is_empty() || credit_side.is_empty() {
        println!("  journal      skipped (no chart of accounts yet)");
        return Ok(());
    }

    let mut entry_stmt = tx.prepare(
        "INSERT INTO accounting_journal_entries
         (id, organization_id, number, entry_date, memo, reference, source, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,'seed',?7,?7)",
    )?;

    let mut line_stmt = tx.prepare(
        "INSERT INTO accounting_journal_lines
         (id, entry_id, account_id, debit_minor, credit_minor, memo, position)
         VALUES (?1,?2,?3,?4,?5,NULL,?6)",
    )?;

    let existing: i64 = tx.query_row(
        "SELECT COUNT(*) FROM accounting_journal_entries WHERE organization_id = ?1",
        params![org],
        |r| r.get(0),
    )?;

    for i in 0..n {
        let id = uuid();
        let amount: i64 = rng.gen_range(50_000..5_000_000) * 100;

        entry_stmt.execute(params![
            id,
            org,
            format!("JE-{:05}", existing as usize + i + 1),
            date_within(rng, 365 * 2),
            pick(rng, names::JOURNAL_MEMOS),
            format!("REF-{:06}", i + 1),
            now(),
        ])?;

        let d = pick(rng, &debit_side);
        let c = pick(rng, &credit_side);

        line_stmt.execute(params![uuid(), id, d.0, amount, 0i64, 0i64])?;
        line_stmt.execute(params![uuid(), id, c.0, 0i64, amount, 1i64])?;
    }

    println!("  journal      {n} (+ {} lines)", n * 2);
    Ok(())
}

/// The operational modules: projects, support, orders, bookings, subscriptions,
/// purchasing, procurement and fulfillment.
///
/// These are grouped into one pass because they share a shape — a reference, a
/// counterparty, a status and a date — and because the point of seeding them is
/// the same: a module with an empty table cannot be reviewed, so every screen in
/// the product should have something behind it.
#[allow(clippy::too_many_arguments)]
fn seed_operations(
    tx: &Transaction,
    rng: &mut StdRng,
    org: &str,
    currency: &str,
    plan: &Plan,
    customers: &[String],
    vendors: &[String],
    departments: &[String],
    docs: &[SeedDoc],
) -> rusqlite::Result<()> {
    // The team, so an appointment is held by a real member of staff rather than
    // by a typed name that matches nobody.
    let seats: Vec<i64> = {
        let mut stmt = tx.prepare(
            "SELECT id FROM organization_members WHERE organization_id = ?1 AND active = 1",
        )?;
        let rows = stmt
            .query_map(params![org], |r| r.get(0))?
            .collect::<rusqlite::Result<Vec<i64>>>()?;
        rows
    };

    // -- projects ----------------------------------------------------------
    let mut stmt = tx.prepare(
        "INSERT INTO projects_records
         (id, organization_id, name, customer, code, status, billing_method,
          budget_minor, hourly_rate_minor, starts_on, ends_on, description,
          customer_id, department_id, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,NULL,?12,?13,?14,?14)",
    )?;

    for i in 0..plan.projects {
        let start = date_within(rng, 500);
        stmt.execute(params![
            uuid(), org,
            format!("{} — {}", pick(rng, names::PROJECTS), pick(rng, names::CITIES)),
            names::company(rng, i),
            format!("PRJ-{:04}", i + 1),
            pick(rng, &["planned", "active", "on_hold", "completed"]),
            pick(rng, &["fixed", "hourly", "milestone"]),
            rng.gen_range(5_000_000..900_000_000i64) * 100,
            rng.gen_range(15_000..90_000i64) * 100,
            start.clone(),
            add_days(&start, rng.gen_range(60..500)),
            if customers.is_empty() { None } else { Some(pick(rng, customers).clone()) },
            if departments.is_empty() { None } else { Some(pick(rng, departments).clone()) },
            now(),
        ])?;
    }
    println!("  projects     {}", plan.projects);

    // -- support tickets ---------------------------------------------------
    let mut stmt = tx.prepare(
        "INSERT INTO support_tickets
         (id, organization_id, reference, subject, requester, requester_email, customer_id,
          category, priority, status, assigned_to, due_on, resolved_on, description, resolution, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,?12,?13,NULL,NULL,?14,?14)",
    )?;

    for i in 0..plan.tickets {
        let status = *pick(rng, &["open", "in_progress", "waiting", "resolved", "closed"]);
        let raised = date_within(rng, 240);
        let (first, last) = names::person(rng, i);
        stmt.execute(params![
            uuid(), org,
            format!("TKT-{:05}", i + 1),
            pick(rng, names::TICKET_SUBJECTS),
            format!("{first} {last}"),
            format!("{}@{}.co.tz", names::slug(&first), names::slug(&last)),
            if customers.is_empty() { None } else { Some(pick(rng, customers).clone()) },
            pick(rng, &["billing", "technical", "delivery", "account", "other"]),
            pick(rng, &["low", "normal", "high", "urgent"]),
            status,
            format!("{} {}", names::person(rng, i + 7).0, names::person(rng, i + 3).1),
            add_days(&raised, 7),
            if status == "resolved" || status == "closed" { Some(add_days(&raised, rng.gen_range(1..14))) } else { None },
            now(),
        ])?;
    }
    println!("  tickets      {}", plan.tickets);

    // -- cart orders -------------------------------------------------------
    let mut stmt = tx.prepare(
        "INSERT INTO cart_orders
         (id, organization_id, number, customer_id, customer_name, channel, status,
          ordered_on, required_on, subtotal_minor, total_minor, currency, document_id, notes, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,?12,?13,NULL,?14,?14)",
    )?;

    for i in 0..plan.orders {
        let ordered = date_within(rng, 300);
        let subtotal: i64 = rng.gen_range(80_000..40_000_000) * 100;
        let total = (subtotal as f64 * 1.18) as i64;
        // Roughly half have been billed, which is what makes "not yet
        // invoiced" a question worth asking on the orders screen.
        let billed = rng.gen_bool(0.5) && !docs.is_empty();
        stmt.execute(params![
            uuid(), org,
            format!("ORD-{:06}", i + 1),
            if customers.is_empty() { None } else { Some(pick(rng, customers).clone()) },
            names::company(rng, i),
            pick(rng, &["web", "phone", "walk_in", "field"]),
            pick(rng, &["pending", "confirmed", "packed", "shipped", "delivered", "cancelled"]),
            ordered.clone(),
            add_days(&ordered, rng.gen_range(3..30)),
            subtotal, total, currency,
            if billed { Some(pick(rng, docs).id.clone()) } else { None },
            now(),
        ])?;
    }
    println!("  orders       {}", plan.orders);

    // -- bookings ----------------------------------------------------------
    let mut stmt = tx.prepare(
        "INSERT INTO bookings_appointments
         (id, organization_id, service, customer, staff, status, starts_at,
          duration_minutes, location, price_minor, notes, customer_id, staff_member_id, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,NULL,?11,?12,?13,?13)",
    )?;

    for i in 0..plan.bookings {
        let day = date_within(rng, 120);
        let (first, last) = names::person(rng, i);
        stmt.execute(params![
            uuid(), org,
            pick(rng, names::SERVICES),
            format!("{first} {last}"),
            format!("{} {}", names::person(rng, i + 5).0, names::person(rng, i + 11).1),
            pick(rng, &["scheduled", "confirmed", "completed", "no_show", "cancelled"]),
            format!("{} {:02}:00:00", day, rng.gen_range(8..17)),
            *pick(rng, &[30i64, 45, 60, 90, 120]),
            pick(rng, names::LOCATIONS),
            rng.gen_range(20_000..800_000i64) * 100,
            if customers.is_empty() { None } else { Some(pick(rng, customers).clone()) },
            if seats.is_empty() { None } else { Some(*pick(rng, &seats)) },
            now(),
        ])?;
    }
    println!("  bookings     {}", plan.bookings);

    // -- subscriptions -----------------------------------------------------
    let mut stmt = tx.prepare(
        "INSERT INTO billing_subscriptions
         (id, organization_id, customer, plan_name, status, interval, amount_minor,
          currency, started_on, next_charge_on, ends_on, notes, customer_id, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,NULL,NULL,?11,?12,?12)",
    )?;

    for i in 0..plan.subscriptions {
        let started = date_within(rng, 700);
        stmt.execute(params![
            uuid(), org,
            names::company(rng, i),
            pick(rng, &["Starter", "Professional", "Enterprise", "Site licence"]),
            pick(rng, &["active", "active", "paused", "cancelled", "past_due"]),
            pick(rng, &["monthly", "quarterly", "yearly"]),
            rng.gen_range(50_000..3_000_000i64) * 100,
            currency,
            started.clone(),
            add_days(&started, 30),
            if customers.is_empty() { None } else { Some(pick(rng, customers).clone()) },
            now(),
        ])?;
    }
    println!("  subs         {}", plan.subscriptions);

    // -- purchase orders ---------------------------------------------------
    let vendor_names: Vec<String> = (0..plan.vendors.max(1)).map(|i| names::vendor(rng, i)).collect();

    let mut stmt = tx.prepare(
        "INSERT INTO purchasing_orders
         (id, organization_id, number, vendor, status, ordered_on, expected_on,
          total_minor, currency, reference, notes, vendor_id, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,NULL,?11,?12,?12)",
    )?;

    for i in 0..plan.purchase_orders {
        let ordered = date_within(rng, 400);
        stmt.execute(params![
            uuid(), org,
            format!("PO-{:06}", i + 1),
            pick(rng, &vendor_names),
            pick(rng, &["draft", "sent", "acknowledged", "received", "cancelled"]),
            ordered.clone(),
            add_days(&ordered, rng.gen_range(7..60)),
            rng.gen_range(200_000..60_000_000i64) * 100,
            currency,
            format!("REQ-{:05}", rng.gen_range(1..9999)),
            if vendors.is_empty() { None } else { Some(pick(rng, vendors).clone()) },
            now(),
        ])?;
    }
    println!("  purchase POs {}", plan.purchase_orders);

    // -- procurement requests ----------------------------------------------
    let dept_names = ["Administration", "Finance", "Operations", "Engineering", "IT", "Human Resources"];

    let mut stmt = tx.prepare(
        "INSERT INTO procurement_requests
         (id, organization_id, reference, requested_by, department, title, status,
          priority, estimated_minor, requested_on, needed_by, justification,
          department_id, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,NULL,?12,?13,?13)",
    )?;

    for i in 0..plan.purchase_requests {
        let asked = date_within(rng, 300);
        let (first, last) = names::person(rng, i);
        stmt.execute(params![
            uuid(), org,
            format!("PR-{:06}", i + 1),
            format!("{first} {last}"),
            pick(rng, &dept_names),
            pick(rng, names::REQUESTS),
            pick(rng, &["draft", "submitted", "approved", "rejected", "ordered"]),
            pick(rng, &["low", "normal", "high"]),
            rng.gen_range(50_000..25_000_000i64) * 100,
            asked.clone(),
            add_days(&asked, rng.gen_range(7..45)),
            if departments.is_empty() { None } else { Some(pick(rng, departments).clone()) },
            now(),
        ])?;
    }
    println!("  requests     {}", plan.purchase_requests);

    // -- shipments ---------------------------------------------------------
    let mut stmt = tx.prepare(
        "INSERT INTO fulfillment_shipments
         (id, organization_id, reference, customer, carrier, tracking_number, status,
          shipped_on, delivered_on, packages, weight_kg, notes, customer_id, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,?7,?8,?9,?10,?11,NULL,?12,?13,?13)",
    )?;

    for i in 0..plan.shipments {
        let status = *pick(rng, &["pending", "in_transit", "delivered", "returned"]);
        let shipped = date_within(rng, 200);
        stmt.execute(params![
            uuid(), org,
            format!("SHP-{:06}", i + 1),
            names::company(rng, i),
            pick(rng, &["DHL", "Posta Tanzania", "Own fleet", "Superdoll", "Agility"]),
            format!("TZ{:09}", rng.gen_range(1u64..999_999_999)),
            status,
            shipped.clone(),
            if status == "delivered" { Some(add_days(&shipped, rng.gen_range(1..12))) } else { None },
            rng.gen_range(1..40i64),
            rng.gen_range(5..4000i64),
            if customers.is_empty() { None } else { Some(pick(rng, customers).clone()) },
            now(),
        ])?;
    }
    println!("  shipments    {}", plan.shipments);

    // -- recurring invoice profiles ----------------------------------------
    let mut stmt = tx.prepare(
        "INSERT INTO invoicing_recurring
         (id, organization_id, customer_id, title, interval, next_run_on, ends_on,
          amount_minor, currency, status, issued_count, notes, created_at, updated_at)
         VALUES (?1,?2,?3,?4,?5,?6,NULL,?7,?8,?9,?10,NULL,?11,?11)",
    )?;

    for i in 0..plan.recurring {
        stmt.execute(params![
            uuid(), org,
            if customers.is_empty() { None } else { Some(pick(rng, customers).clone()) },
            pick(rng, names::RECURRING),
            pick(rng, &["monthly", "quarterly", "yearly"]),
            add_days(&date_within(rng, 20), rng.gen_range(1..40)),
            rng.gen_range(100_000..8_000_000i64) * 100,
            currency,
            pick(rng, &["active", "active", "paused"]),
            rng.gen_range(0..24i64),
            now(),
        ])?;
    }
    println!("  recurring    {}", plan.recurring);

    let _ = vendors;
    Ok(())
}

fn report(conn: &Connection, org: &str) -> rusqlite::Result<()> {
    println!("\nrows now held for this organization:");

    for table in [
        "procurement_vendors",
        "org_departments",
        "crm_customers",
        "invoicing_items",
        "inventory_stock",
        "assets_records",
        "invoicing_documents",
        "invoicing_payments",
        "expenses_records",
        "accounting_journal_entries",
    ] {
        let n: i64 = conn.query_row(
            &format!("SELECT COUNT(*) FROM {table} WHERE organization_id = ?1"),
            params![org],
            |r| r.get(0),
        )?;
        println!("  {:<28}{}", table, n);
    }

    let lines: i64 = conn.query_row(
        "SELECT COUNT(*) FROM invoicing_lines WHERE document_id IN
           (SELECT id FROM invoicing_documents WHERE organization_id = ?1)",
        params![org],
        |r| r.get(0),
    )?;
    println!("  {:<28}{}", "invoicing_lines", lines);

    let jlines: i64 = conn.query_row(
        "SELECT COUNT(*) FROM accounting_journal_lines WHERE entry_id IN
           (SELECT id FROM accounting_journal_entries WHERE organization_id = ?1)",
        params![org],
        |r| r.get(0),
    )?;
    println!("  {:<28}{}", "accounting_journal_lines", jlines);

    Ok(())
}
