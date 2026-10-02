# silvershop/vat-compliance

A VAT compliance layer for [SilverShop](https://github.com/silvershop/silvershop-core).

SilverShop core already does **per-product tax** — a `TaxClass` (title + rate) on each product, applied
per line by `FlatTax` and summarised per rate by [silvershop/invoicing](https://github.com/silvershop/silvershop-invoicing).
This module adds the **compliance** features that sit on top of that: things a real shop needs to charge
and document VAT correctly.

> **Scope at launch: shipping VAT, region-neutral.** The broader name reflects the roadmap
> (see below). Today the module ships one feature — taxing shipping — which applies in any VAT/GST
> jurisdiction. The EU-specific rules (VIES, reverse-charge, OSS) are planned, not yet shipped.

## Requirements

- PHP 8.3+
- silverstripe/framework ^6
- silvershop/core ^6
- silvershop/invoicing *(optional)* — when installed, taxed shipping is folded into its per-rate VAT
  breakdown. Without it, the "Shipping tax class" setting still appears but has nothing to tag.

## Installation

```bash
composer require silvershop/vat-compliance
```

Then run `dev/build?flush=1`.

## v1 — Taxing shipping

Out of the box, SilverShop leaves **shipping untaxed** (both `FlatTax` and invoicing's VAT summary tax
item lines only). Many jurisdictions require delivery charges to carry VAT at a given rate.

Go to **Settings → Main → VAT → Shipping tax class** and pick one of your core tax classes. From then on:

- the shipping charge is taxed at that class's rate;
- on invoices and credit memos the shipping amount joins the matching rate in the per-rate VAT
  breakdown (via silvershop/invoicing).

Leave the dropdown empty to keep shipping untaxed (the default). A 0% class also means no shipping VAT.

Prices are treated as **VAT-inclusive** (the SilverShop/EU B2C convention): the shipping line's gross
amount is unchanged and the VAT contained in it is reported in the breakdown.

### Custom shipping modifiers

By default the shipping tax class is applied to `SilverShop\Shipping\ShippingFrameworkModifier`. If your
shop uses a different shipping `OrderModifier`, add its class:

```yaml
SilverShop\VatCompliance\Extension\TaxLineExtension:
  shipping_modifier_classes:
    - 'My\Shop\MyShippingModifier'
```

Subclasses of a listed class match too.

## How it works

- `SiteConfigVatExtension` adds the `ShippingTaxClass` has_one + the CMS dropdown, and exposes
  `SiteConfig::getShippingTaxRate(): ?float`.
- `TaxLineExtension` is applied to invoicing's `ShopInvoiceLine` / `ShopCreditMemoLine` (only when
  invoicing is installed). As each line is snapshotted, invoicing fires `updateSnapshotLine($source, $order)`;
  when `$source` is a configured shipping modifier and a shipping tax class is set, the line's `TaxRate`
  is set to that rate.
- invoicing's `DocumentLineSnapshotter::snapshotTaxSummary()` then includes `Kind='Modifier'` lines that
  carry a `TaxRate > 0`, so taxed shipping appears in the per-rate VAT breakdown alongside the goods.

## Roadmap

- **v2 — EU B2B:** VAT number field on the customer/address, VIES validation (cached, soft-fail),
  reverse-charge (valid foreign-EU business number → 0% + the mandatory invoice note).
- **v3 — EU destination / OSS:** destination-based B2C rates with OSS thresholds, per-country rate
  tables, seller + buyer VAT numbers on the invoice.
- **Region architecture:** pluggable region rulesets (EU first; UK/CH/… added as rulesets, not forks).

## License

BSD-3-Clause
