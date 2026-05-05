# Changelog

All notable changes to the Buckaroo PrestaShop module are documented in this file.

## v5.2.0

### Added
- BTI-605 Add support for Google Pay as a payment method.

### Improved
- BTI-101 Improve order state management logic.
- BTI-644 Remove API version setting for In3 payment method (enforce V3 API).
- BTI-645 Hide phone number field for In3/Riverty transactions when already provided in the address.
- BTI-867 Limit loading of JavaScript files to required PrestaShop pages.
- BTI-866 Reduce excessive logging introduced in v5.1.0.

### Fixed
- BTI-146 Fix issue where refunds initiated from Buckaroo Plaza do not update correctly in PrestaShop 9.
- BTI-109 Fix incorrect gift card configuration label and limited Grouped option in PrestaShop.
- BTI-735 Fix Riverty birth date format and repair broken terms and conditions link.
- BTI-833 Fix refund handling inconsistencies and cart duplication after successful Buckaroo payment.
- BTI-890 Fix payment fee not included in checkout totals but showing after order confirmation.
- BTI-875 Fix translations for BNPL payment methods and remove unnecessary text.

## v5.1.0

### Changed
- BTI-100 Update iDEAL to the co-branded payment method name iDEAL | Wero.

## v5.0.0

### Added
- BP-5140 Add support for the Wero payment method.
- BP-5137 Add support for the Twint payment method.
- BP-5139 Add support for the Bizum payment method.
- BP-5138 Add support for the Swish payment method.
- BP-4522 Add compatibility with PrestaShop 9.
- BP-3688 Introduce support for percentage-based payment fees.

### Improved
- BP-5130 Enhance container service access for PrestaShop 9.0.1.
- BP-4528 Include customer email in gift card transactions to enable retrieval after refunds.
- BP-5131 Improve CSRF token handling compatibility.
- BP-5132 Refactor payment processing to improve error handling.
- BP-5134 Refactor controllers and admin methods to use dependency injection.

### Fixed
- BP-5180 Fix refunds sent to Buckaroo not marked as refunded in PrestaShop.
- BP-5187 Fix order status not updating in admin after a partial refund.
- BP-5146 Fix duplicate payment fee and fee tax lines when switching payment methods.
- BP-5183 Fix error opening product details in PrestaShop 9 caused by BuckarooIdinTabType.
- BP-5145 Fix decimal values not being accepted for payment fees.
- BP-5148 Fix Buckaroo menu item overlapping PrestaShop 9 admin sidebar.
- BP-5210 Fix gift card refund error.
- BP-5147 Fix order totals showing lower than actual amount when using percentage-based payment fees.

## v4.5.0

- BP-3854 Add support for PrestaShop 8.2.0 and 8.2.1.
- BP-4235 Use new payment method logos for PayPerEmail, Transfer, and Giftcard.
- BP-4248 Rebrand Knaken Settle to goSettle.
- BP-4353 Update PHP SDK version used as a base for the plugin.
- BP-4256 Remove iDEAL show issuers option(s).
- BP-3783 Remove Giropay (discontinued).
- BP-4544 Rebrand iDEAL In3 back to In3.
- BP-4566 Add support for Billink V2 API.
- BP-4235 Fix failed/rejected Card payment redirect to payment selection when possible.

## v4.4.0

- BP-3743 Add support for PrestaShop 8.1.7.
- BP-3602 Add payment method Blik.
- BP-3758 Add Blik configs into the upgrade file.
- BP-3636 Add option to enable/disable automatic refunds via Buckaroo Plaza.
- BP-3672 Rename payment method Riverty/Afterpay to Riverty.
- BP-3713 Update payment method logo from Riverty/Afterpay to Riverty.
- BP-3691 Security enhancement for Axios requests.
- BP-3744 Fix missing translation for Riverty min/max order amount when using combined B2C/B2B.

## v4.3.0

- Add support for PrestaShop 8.1.6.
- BP-3506 Fix API stopped working error 2006.
- BP-3431 Fix prevention of transaction requests without selecting an issuer.
- BP-3480 Fix follow-redirects Proxy-Authorization header kept across hosts.
- BP-3509 Fix status changed from in backorder not paid to paid with backorders.
- BP-3482 Fix payment fee not visible in some places.
- BP-3520 Fix PrestaShop language support.
- BP-3512 Remove logo selection for In3 and iDEAL In3.
- BP-3503 Remove payment method Tinka.

## v4.2.0

- BP-3432 Add support for PrestaShop 8.1.4.
- BP-3439 Add product image URLs for Riverty/AfterPay invoices.
- BP-3464 Add payment method Knaken Settle.
- BP-3456 Update payment method logos.
- BP-3455 Remove button display type setting for cards.
- BP-3459 Remove old Mister Cash name references for Bancontact.
- BP-3431 Fix prevention of transaction requests without selecting an issuer.
- BP-3429 Fix new installation SQL errors caused by older MySQL versions.
- BP-3359 Fix Vite dev server option.
- BP-3419 Fix iDEAL without issuer not sending ContinueOnIncomplete (iDEAL 2.0 backward compatibility).
- BP-3410 Fix duplicate order when Buckaroo payment fails.
- BP-3478 Fix payment fee configuration causing error 500 for payment attempts (Klarna, Billink, Riverty).
- BP-3473 Fix status changed from in backorder not payed to payed instead of in backorder (paid).
- BP-3472 Fix inability to use decimals for payment fee settings.

## v4.1.1

- BP-3353 Enhanced security update.
- BP-3355 Fix Billink error: No recipient category found.

## v4.1.0

- Compatible from PrestaShop 1.7.x up to 8.1.2.
- BP-2985 Add payment method Multibanco.
- BP-3016 Add payment method MB WAY.
- BP-3091 Add financial warning setting for BNPL methods.
- BP-3108 Improve visuals for PayByBank, iDEAL, and CreditCards.
- BP-2995 and BP-3055 In3 V3 API improvements.
- BP-3028 Remove BIC/IBAN fields for Giropay.
- BP-3147 Add option to hide iDEAL issuer selection (iDEAL 2.0 preparation).
- BP-3131 Improve/refactor parts of the code.
- BP-3233 Update Revolut logos.
- BP-3264 Fix Klarna payment method name.
- BP-3271 Remove unsupported bank logos for Pay By Bank.
- BP-3275 Change Klarna payment flow.
- BP-3038 Fix Tinka frontend error.
- BP-3104 Fix payment methods visibility by specific countries.
- BP-3216 Fix modal CSS class overriding PrestaShop CSS settings.
- BP-3160 Fix Klarna transaction error: Configuration not found for Payment ID.
- BP-3241 Fix refunds for some payment methods from PrestaShop admin area.

## v4.0.1

- BP-3043 Fix issue creating products.

## v4.0.0

### Important
- This release introduced major code and design changes.
- Upgrade from older major versions was not supported; a clean installation was required.

### Added and Changed
- Compatible from PrestaShop 1.7.x up to 8.1.2.
- Plugin architecture and UI were fully refactored.
- Added direct refund initiation from PrestaShop admin to Buckaroo Plaza.
- Added payment method ordering by country.
- Added fixed and percentage payment fee configuration.
- Added min/max order amount display conditions for payment methods.
- Added support for PayPal Seller Protection.
- Updated iDEAL issuers (YourSafe, N26, Nationale Nederlanden).
- Added payment methods: Apple Pay, Alipay, Billink, EPS, In3 (V3 API), PayByBank, Payconiq, PayPerEmail, Przelewy24, Trustly, Tinka, WeChat Pay.
- Added card brands: Maestro, VISA Elektron, Carte Bancaire, Carte Bleue, Nexi, Dankort.
- Switched payment logos from PNG to SVG.
- Renamed Creditcards to Credit and debit card.
- Updated software header version reporting in Buckaroo requests.

### Fixed
- BP-1319 Fix empty cart on browser back with Klarna and PayPal.
- BP-2419 Fix SEPA payments.
- BP-2420 Fix redirect failures.
- BP-2476 Fix Riverty/AfterPay payment method.
- BP-2615 Fix Keep Cart Alive issues.
- BP-2577 Fix configuration page save behavior.
- BP-2741 Fix refund issue redirecting to internal server error 500.
- BP-2746 Fix partial refund issues.

## v3.4.0

- SQL error fix when no refund transaction is available.
- BP-1328 Fix wrong refund value when previous refund value was modified.
- BP-1327 Fix missing validation message for payment fields in checkout.
- BP-1321 Fix various UI problems.
- BP-1320 Add cookie SameSite switch.
- BP-1322 Fix refund message.
- BP-1354 Fix issues after installation.
- BP-1318 Fix warnings and notices during install and payment.
- BP-1232 Fix session issue with PrestaShop 1.7.8.2.
- BP-1233 Fix failed second request in partial refund flow.
- BP-1189 Update Sofort logo.
- Improve release archive packaging.
- Remove duplicate language setting.
- BP-1138 Fix reported errors and warnings.
- BP-1081 Fix payment fee not included in payment.
- BP-1092 Fix Klarna phone parameter problem.
- BP-1066 Fix order status not changing automatically.
- BP-987 Update SOAP certificate.

## v3.3.10

- BP-1041 Add translation for IDIN.
- BP-1033 Fix Klarna not working for a specific merchant.
- Fix icon path.
- Change icon size to 32x24.

## v3.3.9

- BP-820 Add payment method Belfius.
- BP-804 Add verification method iDIN (age verification).
- BP-919 Fix error notices in module settings.
- BP-908 Fix Buckaroo payments and refunds tab display issue.
- BP-909 Fix iDIN state not being remembered after successful identification.
- BP-920 Fix partial refund being impossible.
- BP-970 Add CreditCard brand PostePay.
- BP-965 Fix Plaza refunds not visible in PrestaShop.
- BP-941 Fix PayPal V2 cancellation returning to homepage.
- BP-948 Fix payment methods not working/visible.
