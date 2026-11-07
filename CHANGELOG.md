# Changelog

## 1.1.0 — 2026-11-07
- **Conditional fields**: any field can be set to show — and only then be required — when another select / radio / checkbox field has a chosen answer ("Papar hanya jika … dijawab …" in the builder, "Bersyarat" badge). Typical use: a required payment-receipt upload only for a paid category.
- Hidden conditional fields are disabled in the browser and ignored by the server (no validation, uploads discarded).
- Admin edit / manual-add forms no longer let the browser block saving on required file fields (server still validates).

## 1.0.0 — 2026-11-07
First public release (generalised from the Perepat Borang 1.1.0 site build).
- Form builder: 14 field types, drag-to-sort, templates (Khatan Perdana, Program/Kursus, Sukarelawan, kosong), copy form
- Per-form settings: programme info card, open/close datetime, quota with close or wait-list, unique field, reference prefix, age range checked on the programme date
- Registrations: statuses, internal notes, edit, manual add, bulk actions, WhatsApp link, CSV export (Excel-safe), printable attendance list
- Optional hero slide for open forms via `masjid_hero_slides` (Slaid Utama Masjid) and `perepat_hero_slides`
- Site settings: masjid name, colours, Font Awesome mode, IP source, uninstall data removal; private-folder exposure check
- Theme-independent front end (scoped CSS under `.pb-ui`), tested on Twenty Twenty-Five
- Built-in GitHub Releases updater; refuses to activate alongside the old site-specific build
