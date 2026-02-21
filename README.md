# HNH Invoices System

Custom WooCommerce plugin for automated PDF invoice generation.

> 🚧 Status: **Beta (v0.1.0)** – Active Development

---

## 📌 Project Overview

HNH Invoices System is a custom-built WooCommerce plugin designed to automate invoice generation for online stores.

The project was created to eliminate manual invoice handling and improve operational efficiency in e-commerce environments.

This repository represents the active development version of the plugin.

---

## 🎯 Purpose

- Automate invoice creation after WooCommerce order events
- Generate structured PDF invoices using DOMPDF
- Provide a scalable foundation for future invoice management features
- Build a production-ready invoice automation system

---

## ✨ Implemented Features (v0.1.0)

- Invoice generation trigger (order-based logic)
- PDF rendering via DOMPDF
- Template-based invoice structure
- Modular folder architecture
- Clean separation of business logic and presentation layer

---

## 🛣 Roadmap (Planned Features)

- Admin settings panel
- Custom invoice numbering system
- Automatic email attachment
- Invoice storage and history inside WP Admin
- VAT & tax logic expansion
- Branding customization (logo, company details)
- Multi-language support
- Customer account invoice access

---

## 🏗 Architecture Overview

The plugin follows a modular structure:

woocommerce-auto-invoice-system/
│
├── includes/          # Core logic
├── templates/         # Invoice templates
├── vendor/            # DOMPDF dependency
├── hnh-invoices.php   # Main plugin bootstrap
└── README.md

---

## 🧰 Technical Stack

- PHP 8+
- WordPress Plugin API
- WooCommerce Order Hooks
- DOMPDF (PDF rendering)
- Modular file structure

---

## ⚙️ Requirements

- WordPress 6.x+
- WooCommerce 7.x+
- PHP 8.0+

---

## 🚀 Installation (Development)

Clone repository:

git clone https://github.com/NikolayPG89/woocommerce-auto-invoice-system.git

Copy folder into:

wp-content/plugins/

Activate plugin in WordPress Admin.

---

## 📈 Versioning

This project follows semantic versioning:

- 0.x.x → Development / Beta
- 1.0.0 → First stable production release

Current version: v0.1.0

---

## 👨‍💻 Author

Nikolay PG  
Custom WooCommerce Development Project
