=== B2BKing ERP Sync (JSON Importer) ===
Note: This plugin is shared publicly for portfolio and demonstration purposes only. It is a proprietary solution. Redistribution or commercial use is prohibited without explicit permission from the author.


Contributors: joseluis
Tags: b2bking, woocommerce, erp integration, json importer, rest api, dynamic pricing, phc
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0
License: Proprietary – All Rights Reserved
License URI: https://www.joseluis.dev/license

Plugin for automatic integration between ERPs (such as PHC) and the B2BKing plugin in WooCommerce stores, via JSON and REST API.

== Description ==

This plugin exposes a secure custom endpoint in the WordPress REST API that receives structured pricing rules in JSON format and creates corresponding dynamic pricing rules in the B2BKing plugin for WooCommerce.

It is designed to be used in ERP-to-ecommerce integrations, enabling WooCommerce stores to reflect the same customer-specific pricing rules maintained in external systems like PHC CS or PHC GO.

The plugin interprets incoming data and applies:
- Group-based prices (SkuGeneralTab)
- Personalized percentage discounts per product and customer (Discount Percentage)
- Fixed prices per product and customer (Fixed Price)

It does not manage group assignments or user-role logic. Each rule is applied independently using SKU, user login, and priority as key identifiers.

There is no need for CSV uploads or manual admin actions — pricing sync is automated via API.

== Installation ==

1. Upload the ZIP file via the WordPress admin panel (Plugins > Add New > Upload Plugin).
2. Activate the plugin.
3. Ensure that B2BKing is installed and active.
4. Send the data to the endpoint via POST:
   https://[yourdomain]/wp-json/custom/v1/import-dados-b2bking
   Headers:
   Content-Type: application/json
   X-Auth-Token: [your_secure_token]

== JSON Example (All Rule Types) ==

[
  {
    "RuleType": "GroupPrice",
    "ForWho": "Revendedores",
    "SKU": "SYS-0015300",
    "HowMuch": "28.70",
    "Priority": "3"
  },
  {
    "RuleType": "Discount (Percentage)",
    "ApliesTo": "SYS-0015300",
    "ForWho": "adm_csw",
    "HowMuch": "11",
    "Priority": "1"
  }
]

== Security ==

This plugin uses token-based authentication via the 'X-Auth-Token' header. It is recommended to keep the token secret and rotate it periodically for enhanced security.

== Support ==

This plugin is provided as a custom solution. For support, updates, or integration with other ERP systems, please contact the author directly.

== Author ==

Developed by José Luís – (c) 2025
All rights reserved.


