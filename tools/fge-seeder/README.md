# fge-seeder

Bulk business data for the FGE admin, generated in Rust and written straight
into the Laravel SQLite file.

Seeding lives here rather than in Laravel because volume is the point: a hundred
thousand rows through Eloquent is a hundred thousand model instantiations, and
the run is dominated by PHP's garbage collector rather than by the database.
This writes prepared statements inside one transaction — **397,000 rows in
7 seconds**.

## Build

```bash
cargo build --release
```

## Run

```bash
./target/release/fge-seeder --rows 100000 --fresh
```

| Flag | Default | Meaning |
|---|---|---|
| `--db` | `../../database/database.sqlite` | Path to the Laravel database |
| `--org` | `fge` | Organization slug to seed into |
| `--rows` | `100000` | Approximate total rows across all tables |
| `--fresh` | off | Delete this organization's previously seeded data first |
| `--seed` | `20260813` | Generator seed, so a run is reproducible |

`--rows` is a budget, split across tables in proportions a real business has —
far more document lines than customers. Seeding 500,000 gives roughly 60,000
documents, 120,000 lines, 25,000 stock rows and 20,000 assets.

## What it will not touch

**Website content, websites, users and organization members are never written.**
FGE's site content and its five board members are real records imported from the
legacy system; a seeder that overwrote them would be destroying data, not
generating it. Everything this writes is business data keyed to one
organization, and `--fresh` deletes exactly what it wrote.

## What it guarantees

The data is coherent, not merely present — otherwise a scale test only proves
the pages render:

- every document header equals the sum of its lines;
- every journal entry is a balanced debit/credit pair, so the derived trial
  balance is in balance by construction;
- every stock row points at a real item, every asset at an item, a department
  and a vendor;
- costs precede margins, so nothing sells below what it was bought for.

After seeding it runs `ANALYZE`, without which SQLite keeps using the query
plans it chose when the tables were empty.
