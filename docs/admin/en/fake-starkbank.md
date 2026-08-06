---
title: Fake StarkBank
icon: heroicon-o-qr-code
order: 5
type: group
---

# Fake StarkBank

The **scenario remote control** for the fake StarkBank PIX mesh: cash-in (invoice), venue funding (BR Code payment) and cash-out (transfer), plus the signed webhooks the consuming application receives.

The happy path advances on its own — an invoice turns `paid`, a transfer reaches `success`, a payment settles, all on the read, without a single click here. These pages exist for the deviations the consuming application needs to exercise on demand.

The switchboard is **its own**: turning outage on here takes down the `/v2/*` routes of this fake and **nothing** of the Fake Binance section, which has its own screen and its own table.
