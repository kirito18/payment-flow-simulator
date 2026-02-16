# Payment Flow Simulator (PHP)

A realistic **payment lifecycle simulator** built in pure PHP.

Simulates the core gateway flow:

**authorize → capture → void → refund**

Includes:

-   SQLite persistence
-   State machine enforcement
-   Idempotency handling
-   Structured logging
-   REST-style endpoints
-   End-to-end flow simulation script

> Designed to demonstrate backend knowledge of real-world payment
> systems.

------------------------------------------------------------------------

## ✨ Features

-   ✅ `POST /authorize`
-   ✅ `POST /capture`
-   ✅ `POST /void`
-   ✅ `POST /refund`
-   ✅ `GET /transactions/{id}`
-   ✅ SQLite storage
-   ✅ Idempotency key protection
-   ✅ Status transition validation
-   ✅ Gateway-style JSON responses

------------------------------------------------------------------------

## 🧭 Architecture

~~~mermaid
flowchart LR
  A["Client / Checkout"] -->|POST /authorize| B["Payment API"]
  B --> C["State Machine"]
  C --> D["SQLite Storage"]
  B --> E["Logger"]
  B -->|POST /capture| C
  B -->|POST /refund| C
  B -->|GET /transactions| D
~~~

------------------------------------------------------------------------

## 🚀 Quick Start

### 1) Requirements

-   PHP 8.0+

------------------------------------------------------------------------

### 2) Setup

~~~bash
cp .env.example .env
~~~

------------------------------------------------------------------------

### 3) Run local server

~~~bash
php -S 127.0.0.1:8000 -t public
~~~

------------------------------------------------------------------------

### 4) Run end-to-end simulation

~~~bash
php examples/simulate-flow.php
~~~

------------------------------------------------------------------------

## 🧪 Example Authorization Request

~~~json
{
  "amount_cents": 2599,
  "currency": "USD",
  "description": "Premium subscription",
  "customer": {
    "id": "cust_501",
    "email": "customer@example.com"
  },
  "payment_method": {
    "type": "card",
    "last4": "4242",
    "brand": "visa"
  },
  "metadata": {
    "order_id": "ORD-98765"
  },
  "idempotency_key": "idem_20260215_0001"
}
~~~

------------------------------------------------------------------------

## 🔄 Lifecycle Rules

  Action      Allowed From   Resulting State
  ----------- -------------- -----------------
  authorize   ---            authorized
  capture     authorized     captured
  void        authorized     voided
  refund      captured       refunded

Invalid transitions return **HTTP 409 Conflict**.

------------------------------------------------------------------------

## 🔐 Idempotency

If the same `idempotency_key` is sent twice:

-   First request → processed normally
-   Second request → `409 Duplicate idempotency_key`

This mimics real gateway behavior (Stripe-style).

------------------------------------------------------------------------

## 📁 Project Structure

    payment-flow-simulator/
    ├─ public/
    │  └─ index.php
    ├─ src/
    │  ├─ Config.php
    │  ├─ Logger.php
    │  └─ Payment/
    │     ├─ Id.php
    │     ├─ Storage.php
    │     └─ StateMachine.php
    ├─ storage/
    │  ├─ logs/
    │  └─ db/
    ├─ examples/
    │  ├─ simulate-flow.php
    │  └─ sample-payment.json
    ├─ .env.example
    └─ README.md

------------------------------------------------------------------------

## 🪵 Logging

Logs are written in JSON format:

`storage/logs/app.log`

Example:

~~~json
{
  "ts": "2026-02-15T00:00:00Z",
  "level": "INFO",
  "message": "Authorized",
  "context": {
    "id": "txn_20260215_ab12cd34ef",
    "amount_cents": 2599
  }
}
~~~

------------------------------------------------------------------------

## 🗺️ Roadmap

-   [ ] Partial refunds
-   [ ] Capture method auto/manual behavior
-   [ ] Webhook emitter simulation
-   [ ] Correlation ID tracing
-   [ ] Multi-currency validation

------------------------------------------------------------------------

## 📬 Author

**Rober Lopez**\
Backend & API Integration Specialist · Payments · Automation ·
UX/UI-minded Engineer

-   🌐 Website: https://roberlopez.com\
-   💻 GitHub: https://github.com/kirito18\
-   🔗 LinkedIn: https://www.linkedin.com/in/web-rober-lopez/
