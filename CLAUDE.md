# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A REDCap External Module (EM) named "Stanford Letter Project PDF". It renders a REDCap
project's survey responses ("What Matters Most" advance-directive letter) into a
multi-page PDF using TCPDF, mixing raw TCPDF drawing calls (radio buttons, checkboxes,
text fields) with `writeHTML()` blocks styled by `css/letter_project_pdf.css`.

It is a stripped-down offshoot of a larger "Stanford Letter Project" module — only the
PDF-generation piece was kept here (see `description` in `config.json`).

There is no build step, package.json, test suite, or linter configured in this module.
PHP dependencies are managed via Composer (currently only `tecnickcom/tcpdf`).

## Running / testing changes

This module only runs inside a live REDCap instance (see the repo's docker-compose setup
at the repo root) — there is no standalone runner.

- Entry point for generating the PDF: `GetLetter.php?NOAUTH&record=<id>&action=<P|D|I>`
  - `action=P`: inline in browser + auto-trigger print dialog
  - `action=D`: force download
  - default/`I`: inline in browser, no print dialog
  - This page is also linked from the project's REDCap sidebar as "ADMIN: Letter PDF" (see `config.json` `links.project`) and is auto-triggered via redirect from `redcap_survey_complete()` when the project's configured "Last Survey" instrument is completed.
- To manually smoke-test after editing PDF layout code: hit `GetLetter.php` for a real record ID from within the REDCap project context (it requires `$_REQUEST['record']` and reads `$module` global injected by the EM framework — it cannot be run outside REDCap).
- To reinstall/update TCPDF: `composer install` / `composer update` from this directory.

## Architecture

**Request flow:** `GetLetter.php` → `LetterProjectPDF::setupLetter($record_id)` (in
`LetterProjectPDF.php`) → builds a `LetterPDF` (extends TCPDF, in `LetterPDF.php`) →
calls a sequence of `makeHTMLPageN()` static methods to build each page's HTML, plus
direct TCPDF calls (`RadioButton`, `CheckBox`, `TextField`) for form-widget pages → `GetLetter.php`
calls `$pdf->Output()` with the mode determined by `action`.

**Key files:**
- `LetterProjectPDF.php` — the EM's `AbstractExternalModule` subclass. Hooks:
  `redcap_survey_page_top` (cosmetic CSS injection), `redcap_survey_complete` (redirects
  to the PDF once the project's designated "last survey" instrument is submitted).
  `setupLetter()` pulls the full record via `REDCap::getData(['return_format' => 'json', ...])`,
  then drives PDF page construction in a fixed sequence (pages 1–7; page 3 is split into
  two parts because the care-choices table can overflow a page).
- `LetterPDF.php` — TCPDF subclass. Overrides `Header()`/`Footer()` for the letterhead
  (Stanford barcode image, page numbers, address line). `makeHTMLPageN()` methods are
  static, take `$final_data` (the record's field array) and return raw HTML built with
  heredoc strings interpolating field values directly — no templating engine, no escaping.
  Table-building helpers `makeTableOne()` (care choices) and `makeTableNaturalDeath()`
  pull field labels from `REDCap::getDataDictionary()` for some rows and hardcode label
  HTML for others (search each for the reason in inline comments).
- `tcpdf_include.php` / `config/tcpdf_config_alt.php` — legacy TCPDF standalone bootstrap
  files carried over from the TCPDF examples folder; `LetterPDF.php` actually loads TCPDF
  via Composer's `vendor/autoload.php` and only `require_once`s `tcpdf_include.php` for
  its config side effects.
- `css/letter_project_pdf.css` — the only styling for `writeHTML()`-rendered content;
  inlined into every heredoc HTML block via `file_get_contents()` since TCPDF's HTML
  renderer doesn't support external stylesheets.
- `RestCallRequest.php`, `Test.php` — dead code left over from the parent module. Neither
  is referenced from any other file in this module (`Test.php` calls
  `$module->getFileData()`/`uploadFileData()`, which don't exist on this module's class).
  Don't build on these without first confirming they're actually wired up somewhere.

**Namespace inconsistency:** most files use `Stanford\LetterProjectPDF`, but
`RestCallRequest.php` and `Test.php` still declare the old `Stanford\LetterProject`
namespace, and `GetLetter.php` has a stray `use Stanford\LetterProject\LetterPDF;` that
doesn't match `LetterPDF.php`'s actual namespace (`Stanford\LetterProjectPDF`). This `use`
is a no-op in practice because `GetLetter.php` is itself in the `Stanford\LetterProjectPDF`
namespace, so the unqualified `LetterPDF` reference elsewhere resolves correctly anyway —
but don't copy that `use` line as a pattern.

**Known bug:** in `GetLetter.php`, the generated filename uses `$record_id_` (trailing
underscore typo) instead of `$record_id`, so the output filename never actually contains
the record ID.

**Data model assumption:** `setupLetter()` fetches the record with no `events` filter, then
does `current($final_data)` to grab the first (only) event's data — this only works
correctly for single-event (classic, non-longitudinal) projects, or projects where the
data of interest is guaranteed to be in the first event returned.

**Config:** `config.json` defines one system setting (system-wide debug logging) and two
project settings (project debug logging, and `last-survey` — the instrument whose
completion triggers the auto-redirect to the PDF). `GetLetter` is registered under
`no-auth-pages`, so it's reachable without a REDCap login (relies on the `NOAUTH` query
param convention plus the record ID being effectively an unguessable token).

## Broader reference

For REDCap/External-Modules framework internals (hooks, `REDCap` static API, EM lifecycle,
etc.) not specific to this module, see `../REDCAP_TECHNICAL_REFERENCE.md`.