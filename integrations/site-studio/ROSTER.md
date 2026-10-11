# Public artist roster

Site Studio 0.1.13 keeps public editorial profiles separate from portal account and contract permissions. The public roster is exclusively for official TRB rec artists. The portal export remains restricted to approved TRB accounts and excludes QA fixtures.

On new1.trbrec.com, administrators can use **Editor sito → Roster pubblico** to import verified TRB profiles. Each profile requires a public name, biography, valid image attachment, documented source and an active TRB catalogue release. DDB artists, recent collaborations alone and Sara Rigo are excluded by the owner's corrected instruction. Importing does not create a portal user or change commercial entitlements.

The administrative JSON array accepts `name`, `bio`, `links` (objects with `label` and `url`), `image_id`, up to three `gallery_ids`, `basis` (only `trb`), `catalog_term_id`, optional `last_work` and `source`. Catalogue references require exact public-name agreement. Real image attachments, HTTPS links and active non-takedown TRB releases are validated before saving. Private contacts, original documents and contractual data must not be included.

Editorial IDs start at 1000000001 and persist across reimports. A later portal registration with the same normalized public name reuses that identity and retains release associations. Editorial profiles survive a subsequent portal synchronization. Existing public photo and biography overrides continue to apply through the visual editor.

The previous editorial selection is retained in `trb_studio_editorial_previous`. The import validates the whole batch before saving; a page synchronization error restores the previous selection and resynchronizes its pages. On the first authorized administrator request after this update, entries using the superseded `recent` or `explicit` criteria are backed up privately in `trb_studio_editorial_excluded_20261011`, removed from the active selection and their managed pages returned to draft. The public directory also excludes these criteria on every read, so a later refresh cannot restore them. Media and portal accounts are preserved.

Local validation: run `php integrations/site-studio/test-studio.php` with `TRB_WP_TEST_ROOT` pointing to a clean WordPress installation, plus `php integrations/site-studio/test-artist-catalog.php`. After deployment, verify actual artist pages, image orientation, catalogue associations and a manual portal refresh in the browser. Passing the local harness alone does not establish online publication.
