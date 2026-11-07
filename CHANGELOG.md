# Changelog

## 1.2.0 — 2026-11-07 — security release
Brings the public plugin up to the hardened site build. Recommended for everyone.
- **Uploads**: real on-disk size check; type checked against the image header (not the browser's MIME); images re-encoded through the WordPress image editor (EXIF/GPS and anything appended removed, very large pixel counts refused, long side capped at 2400 px); whole-file scan for PHP / script markup; PDFs must be complete (`%%EOF`) and are refused if they contain JavaScript, Launch / GoToR / SubmitForm / ImportData actions, embedded files, rich media, XFA, or an object stream that cannot be decoded; decompression is capped (zip-bomb safe)
- **Fields**: control and bidi-override characters stripped; explicit length errors; MyKid / IC must start with a real date; strict phone and decimal formats; impossible dates rejected; age-checked dates cannot be in the future; links refused in long-text fields too
- **Anti-bot**: the minimum fill time now uses an HMAC-signed render timestamp (could previously be bypassed by posting an old time); clear message when an upload exceeds the server's `post_max_size`
- **Race conditions**: quota, closing time and duplicate check re-run under a MySQL named lock just before the insert
- Per-IP limit raised to 20 / hour (mobile carriers share IPs) and filterable via `pb_submit_limit`
- CSV formula guard also covers leading whitespace, `|` and `%`
- Client-side size / type check on file fields before upload

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
