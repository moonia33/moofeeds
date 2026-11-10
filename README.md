moofeeds: Facebook, Google Ads and Newsman product feeds for PrestaShop 1.7–9.

<a href="https://github.com/moonia33/moofeeds/releases/latest/download/moofeeds.zip">
	<img alt="Download moofeeds.zip" src="https://img.shields.io/badge/download-moofeeds.zip-blue?style=for-the-badge">
</a>

Or use the direct link: [Download moofeeds.zip (latest release)](https://github.com/moonia33/moofeeds/releases/latest/download/moofeeds.zip)

Author
- moonia — ramunas@inultimo.lt

---

Lithuanian (LT)
================

# SXXL Feeds (moofeeds)

Facebook ir Google Ads prekių feed'ai PrestaShop 9 aplinkoje su batched generavimu, kešu ir administravimo nustatymais.

Pastaba: moofeeds taip pat turi Newsman feed'ą (žr. URL skiltį), kurio nėra pradiniame apraše.

## Funkcijos
- Atskiri CSV feed'ai:
	- Facebook Catalog: `/feed/facebook.csv`
	- Google Ads Business Data: `/feed/google-ads.csv`
	- Newsman: `/feed/newsman.csv`
- Tik aktyvios ir sandėlyje esančios prekės (in-stock).
- Tik pagrindinis produktas (be variacijų) pagal reikalavimą.
- Kainodara: `price` = bazinė kaina su PVM; `sale_price` rodomas tik kai yra nuolaida.
- Tekstų normalizacija į "sentence case" (pavadinimai, brand, kategorijos, aprašymo segmentai).
- `internal_label` — naujasis Facebook laukas. Pildomas sąrašu, pvz., `['summer','trending']`. Modulis automatiškai užpildo iš kelių produkto požymių, taip pat prideda `new` (pagal `PS_NB_DAYS_NEW_PRODUCT`) ir `top` (jei yra pakankamai pardavimų).
- Paveikslėlių dydis: `large_default` (viršelio nuotrauka).
- Failo kešas su ETag/Last-Modified; skaitymo URL visada grąžina paskutinį pilną CSV.
- Cron generatorius su lock'u: vienas kvietimas atlieka pilną ciklą (reset + generavimas iki galo).
- Administravimo UI: token, numatytasis `size`, reset mygtukai, statistika.

## URL'ai
- Skaitymas (visada grąžina paskutinį sugeneruotą failą):
	- `https://<domenas>/<base_uri>feed/facebook.csv`
	- `https://<domenas>/<base_uri>feed/google-ads.csv`
	- `https://<domenas>/<base_uri>feed/newsman.csv`
- Cron generatorius:
	- `https://<domenas>/<base_uri>feed/cron?feed=facebook|googleads|newsman&token=<TOKEN>[&size=...]`

> Pastaba: `<TOKEN>` rasite modulio administravimo puslapyje. Vietoj parametro `size` galite naudoti BO nustatytąją reikšmę.

## Parametrai
- `feed` (privalomas): `facebook`, `googleads` arba `newsman`.
- `token` (privalomas nebent kviečiama iš 127.0.0.1): saugos žetonas.
- `size` (nebūtinas): kiek įrašų apdoroti per vieną DB užklausą (batch). Jei nepaduotas, naudojamas BO numatytasis.

## Generavimo logika
- Vienas cron kvietimas visada atlieka pilną ciklą: pradeda naują generavimą (`.tmp` failas) ir generuoja partijomis po `size` prekių iki galo.
- Kvietimas grąžina JSON:
	- `{"status":"done","file":"...","processed":...}` – pilnai sugeneruota.
	- `{"status":"busy"}` – veikia kitas procesas (lock).
- Generuojant būsena saugoma `*.state.json` (progreso stebėjimui).
- Užbaigus, `.tmp` pervadinama į galutinį `.csv`, `.state.json` pašalinama.
- Senas `.csv` lieka pasiekiamas, kol naujas nėra pilnai sugeneruotas (pervadinimas pakeičia jį atlikus ciklą).

## Crontab pavyzdžiai
Vienas kvietimas pilnai regeneruoja feed'ą. Pakeiskite `<TOKEN>` ir domeną.

```
# Kasdien 05:05
curl -sS 'https://<domenas>/feed/cron?feed=facebook&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=googleads&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=newsman&token=<TOKEN>' > /dev/null
```

## BO nustatymai
Modulio administravimo puslapyje (Modules > Module Manager > moofeeds):
- `Cron token` – peržiūrėti arba regeneruoti.
- `Default batch size (size)` – numatytoji `size` reikšmė, kai nepaduota URL parametruose.
- `Full reset` mygtukai kiekvienam feed'ui – išvalo kešą ir būseną einamam Shop/Language/Currency kontekstui.
- Statistikos lentelė: ar egzistuoja CSV, paskutinio generavimo data, eilučių skaičius.

## Failų vietos
- Kešo failai: `modules/moofeeds/var/cache/<feed>-<shopId>-<langId>-<currencyIso>.csv`
- Laikinas failas: `... .csv.tmp`
- Būsenos failas: `... .state.json`
- Lock failas: `modules/moofeeds/var/lock/<feed>-<shopId>-<langId>-<currencyIso>.lock`

## Atitikties pastabos
- Facebook laukai: `id,title,description,availability,condition,price,link,image_link,brand,sale_price,item_group_id,google_product_category,product_type,mpn,gtin,age_group,gender,color,material,internal_label`.
- Google Ads Business Data laukai (tikslūs pavadinimai, jautrūs raidėms ir tarpams): `ID, ID2, Item title, Final URL, Image URL, Item subtitle, Item description, Item category, Price, Sale price, Contextual keywords, Item address, Tracking template, Custom parameter, Final mobile URL, Android app link, iOS app link, iOS app store ID, Formatted price, Formatted sale price`.
- Kainos formatuojamos kaip `123.45 EUR` (Newsman feed'e — be valiutos, tik skaitinė reikšmė).

## Trikčių šalinimas
- `status=busy`: palaukite, kol baigsis kitas vykdymas; patikrinkite lock failą.
- Timeout: sumažinkite `size`; įsitikinkite, kad reverse-proxy/PHP limitai pakankami (generatorius pats nustato `set_time_limit(0)`).

## Saugumas
- Prieiga prie cron generatoriaus saugoma token'u; be jo leidžiama tik iš `127.0.0.1`.
- Skaitymo URL'ai (`/feed/*.csv`) yra vieši – pritaikyti integracijoms.

---

English (EN)
============

# SXXL Feeds (moofeeds)

Facebook and Google Ads product feeds for PrestaShop 9 with batched generation, file caching, and an admin settings UI.

Note: moofeeds also includes a Newsman feed (see URLs), which is an addition compared to the original text.

## Features
- Separate CSV feeds:
	- Facebook Catalog: `/feed/facebook.csv`
	- Google Ads Business Data: `/feed/google-ads.csv`
	- Newsman: `/feed/newsman.csv`
- Only active, in-stock products.
- Main product only (no combinations) by specification.
- Pricing: `price` = base price incl. VAT; `sale_price` shown only when there’s a discount.
- Sentence-case normalization for names/brand/category/description segments.
- `internal_label` — Facebook's internal labels array, e.g., `['summer','trending']`. The module fills it from a few product features and adds `new` (based on `PS_NB_DAYS_NEW_PRODUCT`) and `top` (if there are enough sales).
- Image size: `large_default` (cover image).
- File cache with ETag/Last-Modified; feed URLs always return the latest complete CSV.
- Cron generator with locking: a single call runs a full cycle (reset + generate to completion).
- Admin UI: token, default `size`, reset buttons, stats.

## URLs
- Read (always returns the last generated file):
	- `https://<domain>/<base_uri>feed/facebook.csv`
	- `https://<domain>/<base_uri>feed/google-ads.csv`
	- `https://<domain>/<base_uri>feed/newsman.csv`
- Cron generator:
	- `https://<domain>/<base_uri>feed/cron?feed=facebook|googleads|newsman&token=<TOKEN>[&size=...]`

> Note: `<TOKEN>` is shown in the module’s admin page. You can omit `size` to use the default from BO.

## Parameters
- `feed` (required): `facebook`, `googleads`, or `newsman`.
- `token` (required unless called from 127.0.0.1): access token.
- `size` (optional): batch size per DB query. Uses default if omitted.

## Generation logic
- A single cron call always runs a full cycle: starts a fresh generation (`.tmp` file) and generates in batches of `size` products until done.
- The call returns JSON:
	- `{"status":"done","file":"...","processed":...}` — fully generated.
	- `{"status":"busy"}` — another process holds the lock.
- Progress state is written to `*.state.json` during generation.
- When finished, `.tmp` is renamed to final `.csv` and `.state.json` is removed.
- The old `.csv` remains available until the new one is fully generated (the rename replaces it at the end of the cycle).

## Crontab examples
A single call fully regenerates a feed. Replace `<TOKEN>` and domain.

```
# Every day at 05:05
curl -sS 'https://<domain>/feed/cron?feed=facebook&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=googleads&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=newsman&token=<TOKEN>' > /dev/null
```

## BO settings
In Modules > Module Manager > moofeeds:
- `Cron token` — view or regenerate.
- `Default batch size (size)` — default batch size when not provided in URL.
- `Full reset` buttons per feed — clears cache and state for the current Shop/Language/Currency context.
- Stats table: whether CSV exists, last generated time, row count.

## File locations
- Cache files: `modules/moofeeds/var/cache/<feed>-<shopId>-<langId>-<currencyIso>.csv`
- Temp file: `... .csv.tmp`
- State file: `... .state.json`
- Lock file: `modules/moofeeds/var/lock/<feed>-<shopId>-<langId>-<currencyIso>.lock`

## Compliance notes
- Facebook fields: `id,title,description,availability,condition,price,link,image_link,brand,sale_price,item_group_id,google_product_category,product_type,mpn,gtin,age_group,gender,color,material,internal_label`.
- Google Ads Business Data fields (exact names, case- and space-sensitive): `ID, ID2, Item title, Final URL, Image URL, Item subtitle, Item description, Item category, Price, Sale price, Contextual keywords, Item address, Tracking template, Custom parameter, Final mobile URL, Android app link, iOS app link, iOS app store ID, Formatted price, Formatted sale price`.
- Prices formatted as `123.45 EUR` (Newsman feed uses numeric prices only, no currency suffix).

## Troubleshooting
- `status=busy`: wait for the other run to finish; check the lock file.
- Timeouts: lower `size`; ensure proxy/PHP limits are sufficient (the generator sets `set_time_limit(0)` itself).

## Security
- Cron generator requires a token; otherwise allowed only from `127.0.0.1`.
- Read URLs (`/feed/*.csv`) are public for integrations.
