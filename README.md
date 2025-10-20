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
- `custom_label_0..4` pildomi tik produkto požymių (features) reikšmėmis.
- Paveikslėlių dydis: `large_default` (viršelio nuotrauka).
- Failo kešas su ETag/Last-Modified; skaitymo URL visada grąžina paskutinį pilną CSV.
- Cron pagrįstas dalinis (batched) generatorius su būsena ir lock'u.
- Administravimo UI: token, numatytieji `size`/`max_steps`, reset mygtukai, statistika.

## URL'ai
- Skaitymas (visada grąžina paskutinį sugeneruotą failą):
	- `https://<domenas>/<base_uri>feed/facebook.csv`
	- `https://<domenas>/<base_uri>feed/google-ads.csv`
	- `https://<domenas>/<base_uri>feed/newsman.csv`
- Cron generatorius:
	- `https://<domenas>/<base_uri>feed/cron?feed=facebook|googleads|newsman&token=<TOKEN>[&size=...&max_steps=...][&reset=1]`

> Pastaba: `<TOKEN>` rasite modulio administravimo puslapyje. Vietoj parametrų `size`/`max_steps` galite naudoti BO nustatytąsias reikšmes.

## Parametrai
- `feed` (privalomas): `facebook`, `googleads` arba `newsman`.
- `token` (privalomas nebent kviečiama iš 127.0.0.1): saugos žetonas.
- `size` (nebūtinas): kiek įrašų apdoroti per vieną žingsnį (batch). Jei nepaduotas, naudojamas BO numatytasis.
- `max_steps` (nebūtinas): kiek žingsnių maksimaliai atlikti vieno cron kvietimo metu. Jei nepaduotas, naudojamas BO numatytasis.
- `reset` (nebūtinas): `1` – pradėti naują generavimo ciklą (ištrina ankstesnį `.csv`, `.tmp`, `.state.json`).

## Generavimo logika
- Generatorius veikia partijomis: ima po `size` prekių nuo paskutinio `last_id`.
- Vienas kvietimas atlieka iki `max_steps` žingsnių ir grąžina JSON:
	- `{"status":"partial","last_id":...,"processed":...}` – dar nebaigta, reikia tęsti kitu kvietimu.
	- `{"status":"done","file":"..."}` – pilnai sugeneruota.
	- `{"status":"busy"}` – veikia kitas procesas (lock).
- Būsena saugoma `*.state.json`, todėl periodiniai kvietimai tęsia nuo ten, kur sustojo.
- Užbaigus, `.tmp` pervadinama į galutinį `.csv`, `.state.json` pašalinama.
- Jei galutinis `.csv` egzistuoja ir nepaduotas `reset=1`, cron nieko nepergeneruoja (grąžina `done`).

## Crontab pavyzdžiai
Žemiau pateikiami pavyzdžiai naudojant BO numatytuosius `size`/`max_steps`. Pakeiskite `<TOKEN>` ir domeną.

1) Dieninis „kickoff“ su `reset` (pradeda naują ciklą):
```
# Kasdien 05:05
curl -sS 'https://<domenas>/feed/cron?feed=facebook&reset=1&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=googleads&reset=1&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=newsman&reset=1&token=<TOKEN>' > /dev/null
```

2) Worker kas minutę (ar kas 5 min.) – užbaigia ciklą partijomis:
```
# Kas minutę
curl -sS 'https://<domenas>/feed/cron?feed=facebook&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=googleads&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=newsman&token=<TOKEN>' > /dev/null
```

3) Pilnas regeneravimas vienu kvietimu (jei leidžia timeout'ai):
```
# ~8000 prekių, size=1000, max_steps=8
curl -sS 'https://<domenas>/feed/cron?feed=facebook&reset=1&max_steps=8&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=googleads&reset=1&max_steps=8&token=<TOKEN>' > /dev/null
curl -sS 'https://<domenas>/feed/cron?feed=newsman&reset=1&max_steps=8&token=<TOKEN>' > /dev/null
```

## BO nustatymai
Modulio administravimo puslapyje (Modules > Module Manager > moofeeds):
- `Cron token` – peržiūrėti arba regeneruoti.
- `Default batch size (size)` – numatytoji `size` reikšmė, kai nepaduota URL parametruose.
- `Default steps per call (max_steps)` – numatytoji `max_steps` reikšmė.
- `Full reset` mygtukai kiekvienam feed'ui – išvalo kešą ir būseną einamam Shop/Language/Currency kontekstui.
- Statistikos lentelė: ar egzistuoja CSV, paskutinio generavimo data, eilučių skaičius.

## Failų vietos
- Kešo failai: `modules/moofeeds/var/cache/<feed>-<shopId>-<langId>-<currencyIso>.csv`
- Laikinas failas: `... .csv.tmp`
- Būsenos failas: `... .state.json`
- Lock failas: `modules/moofeeds/var/lock/<feed>-<shopId>-<langId>-<currencyIso>.lock`

## Atitikties pastabos
- Facebook laukai: `id,title,description,availability,condition,price,link,image_link,brand,sale_price,item_group_id,google_product_category,mpn,gtin,custom_label_0..4`.
- Google Ads Business Data laukai: `id,item_title,final_url,image_url,price,sale_price,availability,brand,condition,item_category,mpn,gtin,custom_label_0..4`.
- Kainos formatuojamos kaip `123.45 EUR` (Newsman feed'e — be valiutos, tik skaitinė reikšmė).

## Trikčių šalinimas
- `status=busy`: palaukite, kol baigsis kitas vykdymas; patikrinkite lock failą.
- `status=partial`: tęskite kvietimus (cron worker arba padidinkite `max_steps`).
- Failas nesikeičia: galutinis `.csv` egzistuoja ir nepaduotas `reset=1` – paleiskite `reset`.
- Timeout: sumažinkite `max_steps` arba `size`; įsitikinkite, kad reverse-proxy/PHP limitai pakankami.

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
- `custom_label_0..4` populated from product features only.
- Image size: `large_default` (cover image).
- File cache with ETag/Last-Modified; feed URLs always return the latest complete CSV.
- Cron-based batched generator with state and locking.
- Admin UI: token, default `size`/`max_steps`, reset buttons, stats.

## URLs
- Read (always returns the last generated file):
	- `https://<domain>/<base_uri>feed/facebook.csv`
	- `https://<domain>/<base_uri>feed/google-ads.csv`
	- `https://<domain>/<base_uri>feed/newsman.csv`
- Cron generator:
	- `https://<domain>/<base_uri>feed/cron?feed=facebook|googleads|newsman&token=<TOKEN>[&size=...&max_steps=...][&reset=1]`

> Note: `<TOKEN>` is shown in the module’s admin page. You can omit `size`/`max_steps` to use defaults from BO.

## Parameters
- `feed` (required): `facebook`, `googleads`, or `newsman`.
- `token` (required unless called from 127.0.0.1): access token.
- `size` (optional): batch size per step. Uses default if omitted.
- `max_steps` (optional): maximum steps per cron call. Uses default if omitted.
- `reset` (optional): `1` — start a fresh generation cycle (removes `.csv`, `.tmp`, `.state.json`).

## Generation logic
- The generator works in batches: it takes `size` products after the last `last_id`.
- One request performs up to `max_steps` steps and returns JSON:
	- `{"status":"partial","last_id":...,"processed":...}` — not finished, keep calling.
	- `{"status":"done","file":"..."}` — fully generated.
	- `{"status":"busy"}` — another process holds the lock.
- State is written to `*.state.json`, so periodic calls resume where they left off.
- When finished, `.tmp` is renamed to final `.csv` and `.state.json` is removed.
- If the final `.csv` exists and no `reset=1` is passed, cron won’t regenerate (returns `done`).

## Crontab examples
Using default `size`/`max_steps` from BO; replace `<TOKEN>` and domain.

1) Daily kickoff with `reset` (starts a new cycle):
```
# Every day at 05:05
curl -sS 'https://<domain>/feed/cron?feed=facebook&reset=1&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=googleads&reset=1&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=newsman&reset=1&token=<TOKEN>' > /dev/null
```

2) Worker every minute (or every 5 minutes) — completes the cycle in batches:
```
curl -sS 'https://<domain>/feed/cron?feed=facebook&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=googleads&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=newsman&token=<TOKEN>' > /dev/null
```

3) Full regeneration in one call (subject to timeouts):
```
# ~8000 products, size=1000, max_steps=8
curl -sS 'https://<domain>/feed/cron?feed=facebook&reset=1&max_steps=8&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=googleads&reset=1&max_steps=8&token=<TOKEN>' > /dev/null
curl -sS 'https://<domain>/feed/cron?feed=newsman&reset=1&max_steps=8&token=<TOKEN>' > /dev/null
```

## BO settings
In Modules > Module Manager > moofeeds:
- `Cron token` — view or regenerate.
- `Default batch size (size)` — default batch size when not provided in URL.
- `Default steps per call (max_steps)` — default step count per call.
- `Full reset` buttons per feed — clears cache and state for the current Shop/Language/Currency context.
- Stats table: whether CSV exists, last generated time, row count.

## File locations
- Cache files: `modules/moofeeds/var/cache/<feed>-<shopId>-<langId>-<currencyIso>.csv`
- Temp file: `... .csv.tmp`
- State file: `... .state.json`
- Lock file: `modules/moofeeds/var/lock/<feed>-<shopId>-<langId>-<currencyIso>.lock`

## Compliance notes
- Facebook fields: `id,title,description,availability,condition,price,link,image_link,brand,sale_price,item_group_id,google_product_category,mpn,gtin,custom_label_0..4`.
- Google Ads Business Data fields: `id,item_title,final_url,image_url,price,sale_price,availability,brand,condition,item_category,mpn,gtin,custom_label_0..4`.
- Prices formatted as `123.45 EUR` (Newsman feed uses numeric prices only, no currency suffix).

## Troubleshooting
- `status=busy`: wait for the other run to finish; check the lock file.
- `status=partial`: keep calling (via cron worker) or increase `max_steps`.
- File not changing: final `.csv` exists and no `reset=1` — run with `reset`.
- Timeouts: lower `max_steps`/`size`; ensure proxy/PHP limits are sufficient.

## Security
- Cron generator requires a token; otherwise allowed only from `127.0.0.1`.
- Read URLs (`/feed/*.csv`) are public for integrations.
