<p align="center">
  <a href="https://www.buckaroo.nl">
    <img src="https://raw.githubusercontent.com/buckaroo-it/Media/main/Buckaroo/README.md%20Headers/buckaroo-prestashop-header-rounded.png" alt="Buckaroo — Payments for PrestaShop" width="100%">
  </a>
</p>

<h1 align="center">Buckaroo for PrestaShop</h1>

<p align="center">
  <a href="https://github.com/buckaroo-it/PrestaShop/releases"><img src="https://img.shields.io/github/v/release/buckaroo-it/PrestaShop.svg?label=release" alt="Latest release"></a>
  <a href="https://docs.buckaroo.io/docs/prestashop"><img src="https://img.shields.io/badge/docs-docs.buckaroo.io-1a1a4b.svg" alt="Documentation"></a>
  <a href="https://github.com/buckaroo-it/PrestaShop/releases/latest"><img src="https://img.shields.io/badge/PrestaShop-download-df0067.svg" alt="Download the module"></a>
</p>

<p align="center">
  <a href="#about">About</a> &middot;
  <a href="#requirements">Requirements</a> &middot;
  <a href="#installation">Installation</a> &middot;
  <a href="#upgrade">Upgrade</a> &middot;
  <a href="#configuration">Configuration</a> &middot;
  <a href="#payment-methods">Payment methods</a> &middot;
  <a href="#support">Support</a> &middot;
  <a href="#contribute">Contribute</a>
</p>

---

## About

PrestaShop is an open source e-commerce platform, started in 2005 and used by around 160,000 shops worldwide in around 60 languages.

The Buckaroo module for PrestaShop connects your shop to the Buckaroo payment gateway, so you can start accepting payments within minutes. Buckaroo is a Dutch Payment Service Provider. The module is free to download and every payment method it offers is SEPA proof.

Card payments run through Hosted Fields, which keeps the card entry inside your own checkout instead of redirecting the customer away. Refunds can be started from the PrestaShop admin and are executed directly in Buckaroo Plaza, and you can order the payment methods per country in the checkout.

[Full plugin documentation on docs.buckaroo.io](https://docs.buckaroo.io/docs/prestashop)

---

## Requirements

| Requirement | Supported versions |
|---|---|
| PrestaShop | 1.7.x up to 9.0.1 |
| PHP | 8.0 or higher |

You also need a Buckaroo account. Don't have one yet? [Request an account](https://www.buckaroo.nl/start).

---

## Installation

1. Go to the [releases page](https://github.com/buckaroo-it/PrestaShop/releases) and download the .ZIP file of the latest version.
2. Sign in to your PrestaShop backend and go to **Modules → Module Manager → Upload Module**.
3. Select the .ZIP file. The installation starts automatically.
4. Once installed, the module appears in the Payment section of the Module Manager.

---

## Upgrade

Download the .ZIP file of the latest release and upload it the same way as a new installation. PrestaShop replaces the existing module and keeps your settings.

> [!TIP]
> Always test an upgrade on a staging environment first and check the [release notes](https://github.com/buckaroo-it/PrestaShop/releases) for breaking changes.

---

## Configuration

Sign in to your PrestaShop backend, go to **Modules → Module Manager**, find the Buckaroo module and press **Configure**.

You will need your **Store key** and **Secret key**, which you can find under [API credentials in Buckaroo Plaza](https://plaza.buckaroo.nl/Configuration/Merchant/ApiKeys). The Store key is unique per store, the Secret key applies to your whole account.

Set the module to **Test** while you are trying things out, and to **Live** once you are ready to accept real payments.

Step-by-step instructions: [Configuring the PrestaShop module](https://docs.buckaroo.io/docs/prestashop-configuration)

> [!IMPORTANT]
> If you enable payment fees, check that surcharges are permitted for the methods you use. Under PSD2, surcharging is prohibited for certain consumer payment methods.

---

## Payment methods

The module supports the following payment methods. Each one can be enabled or disabled individually and switched between live and test mode.

| | | |
|---|---|---|
| [Alipay](https://docs.buckaroo.io/docs/alipay) | [Apple Pay](https://docs.buckaroo.io/docs/apple-pay) | [Bancontact](https://docs.buckaroo.io/docs/bancontact) |
| [Bank Transfer](https://docs.buckaroo.io/docs/transfer) | [Belfius](https://docs.buckaroo.io/docs/belfius) | [Billink](https://docs.buckaroo.io/docs/billink) |
| [Bizum](https://docs.buckaroo.io/docs/bizum) | [Blik](https://docs.buckaroo.io/docs/blik) | [Credit and debit cards](https://docs.buckaroo.io/docs/creditcards) |
| [EPS](https://docs.buckaroo.io/docs/eps) | [Giftcards](https://docs.buckaroo.io/docs/giftcards) | [GoSettle](https://docs.buckaroo.io/docs/gosettle) |
| [iDEAL / Wero](https://docs.buckaroo.io/docs/ideal) | [In3](https://docs.buckaroo.io/docs/in3) | [KBC](https://docs.buckaroo.io/docs/kbc) |
| [MB Way](https://docs.buckaroo.io/docs/mb-way) | [Multibanco](https://docs.buckaroo.io/docs/multibanco) | [Pay by Bank](https://docs.buckaroo.io/docs/pay-by-bank) |
| [PayPal](https://docs.buckaroo.io/docs/paypal) | [PayPerEmail](https://docs.buckaroo.io/docs/payperemail) | [Przelewy24](https://docs.buckaroo.io/docs/przelewy24) |
| [Riverty](https://docs.buckaroo.io/docs/riverty) | [Swish](https://docs.buckaroo.io/docs/swish) | [Trustly](https://docs.buckaroo.io/docs/trustly) |
| [Twint](https://docs.buckaroo.io/docs/twint) | [WeChatPay](https://docs.buckaroo.io/docs/wechatpay) | [Wero](https://docs.buckaroo.io/docs/wero) |

> [!IMPORTANT]
> All supported methods appear in the PrestaShop backend, but you need an active Buckaroo subscription for a method before you can offer it in your checkout.

---

## Support

Having trouble? Work through this list before reaching out:

1. Check the [frequently asked questions](https://docs.buckaroo.io/docs/prestashop-faq).
2. Confirm you are on the [latest release](https://github.com/buckaroo-it/PrestaShop/releases).
3. Reproduce the issue with the module in test mode and check the PrestaShop logs.
4. Verify that your push URL is reachable from outside your network. Buckaroo sends push messages from fixed IP addresses and ports, so make sure these are on your allow list. See [push messages](https://docs.buckaroo.io/docs/integration-push-messages) for the current list.

Still stuck? Contact us and include your PrestaShop version, module version, PHP version, the relevant log lines and the transaction key.

- **Bug reports and feature requests:** [open an issue](https://github.com/buckaroo-it/PrestaShop/issues)
- **Technical support:** [support@buckaroo.nl](mailto:support@buckaroo.nl)
- **Phone:** +31 (0)30 711 50 50
- **Gateway status:** [status.buckaroo.io](https://status.buckaroo.io/)

---

## Contribute

We really appreciate it when developers help improve the Buckaroo plugins. Please read our [Contribution Guidelines](https://github.com/buckaroo-it/PrestaShop/blob/master/CONTRIBUTING.md) before opening a pull request, and target the `master` branch.

Found a security issue? Please report it privately to [support@buckaroo.nl](mailto:support@buckaroo.nl) instead of opening a public issue.

---

## Versioning

We follow semantic versioning (`MAJOR.MINOR.PATCH`):

- **MAJOR** — breaking changes that require additional testing and caution.
- **MINOR** — new functionality with limited impact.
- **PATCH** — bug fixes and hotfixes only.

All changes are documented on the [releases page](https://github.com/buckaroo-it/PrestaShop/releases).

---

<p align="center">
  <sub>Made with care by <a href="https://www.buckaroo.nl">Buckaroo</a>.<br>
  This document is subject to change; typos and language errors are possible.</sub>
</p>
