# Artist Store benefits rollout

The portal is the source of entitlement. DDS, DDB12, DDB and DDB-TRB qualify; pending/denied accounts and expired access contracts do not. TRB uses its representative for included services. Role changes take effect on the next Store request; checkout requests fresh verification. No customer list is exported.

The dedicated POST endpoint `/wp-json/trb/v1/artist-benefits` requires the existing DDS shared key (minimum 32 characters), a purpose-bound HMAC, a 120-second timestamp and a single-use nonce. No email or profile is returned; only a request-bound email hash and benefit booleans.

## Activation and QA gate

Deploy both repositories first. Configure the same private key in existing DDS bridge settings on both sites if not already configured. Do not print or commit the key. WooCommerce → Condizioni artista performs the authenticated health check and switches the rollout on. Until then there is no portal banner and no automatic discount claim in emails.

Before declaring completion, use only `andrea.tognassi@trbrec.com` for email QA. Confirm the Store email, verify a matching eligible portal fixture, add services from different categories and verify 50% in cart/checkout including taxes, rounding and quantities. Check a pre-existing Store account, email change, role revocation, ineligible/TRB profiles and network failure. Verify no real payment is made. Test desktop/mobile account, cart and portal panel. If live QA fails, deactivate on both sites and fix before announcing availability.

## Email recommendations

The editorial pass selects zero or one service from a verified, fixed catalog and supplies a verbatim excerpt from its own final review supporting the choice. Invalid/unknown/unsubstantiated suggestions are omitted. No audio means no mastering/production offer. The model cannot set prices, coupon codes or discount terms. The system supplies the current service prerequisites; no claim of melodic assessment is made for text-only material. Previous reviews are not retroactively changed.

Verified catalog sources (2026-09-07):
- https://store.trbrec.com/prodotto/revisione-e-adattamento-autoriale-del-testo/
- https://store.trbrec.com/prodotto/stereo-mastering-professionale/
- https://store.trbrec.com/prodotto/produzione-musicale-essential/

The service facts must be maintained if those offers change. Recommendations are editorial suggestions, not a requirement to buy. TRB receives a contact suggestion instead of a paid offer.
