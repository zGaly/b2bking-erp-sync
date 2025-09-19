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

== JSON Example (All Rule Types with Priority) ==

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
  },
  {
    "RuleType": "Fixed Price",
    "ApliesTo": "SYS-0015300",
    "ForWho": {
      "no": "1234",
      "nome": "Cliente Premium",
      "email": "premium@cliente.com",
      "inativo": false,
      "tipodesc": "VIP",
      "tabelaPrecos": "PREMIUM"
    },
    "HowMuch": "25.50",
    "Priority": "2"
  }
]

== Priority Field ==

The Priority field is now supported in all rule types:
- Range: 1-10 (lower number = higher priority)
- Optional: defaults to 1 if not specified
- Validation: values outside range are automatically adjusted
- Display: appears in B2BKing rule management interface

== Changelog ==

= 2.4 =
* NEW: Complete Priority system support for all rule types
* NEW: Priority field validation and automatic adjustment (1-10)
* NEW: Full B2BKing interface integration for priority display
* IMPROVED: Enhanced success messages include priority information
* IMPROVED: Multiple meta field compatibility for maximum B2BKing support

= 2.3 =
* BREAKING CHANGE: Removed automatic product creation
* REQUIRED: Products must exist in WooCommerce before rule creation
* IMPROVED: Better error messages when products don't exist
* SECURITY: Prevents accidental product creation with incorrect data

= 2.2 =
* Added automatic user creation with complete ERP data
* Added inactive customer filtering
* Added automatic price table to group mapping
* Added structured ERP data support
* Improved error handling and validation

= 2.1 =
* Added automatic product and group creation
* Added percentage discount rule support
* Added basic priority system

= 2.0 =
* Initial REST API implementation
* B2BKing dynamic rules integration
* Token-based authentication

== Security ==

This plugin uses token-based authentication via the 'X-Auth-Token' header. It is recommended to keep the token secret and rotate it periodically for enhanced security.

== Support ==

This plugin is provided as a custom solution. For support, updates, or integration with other ERP systems, please contact the author directly.

== Author ==

Developed by José Luís – (c) 2025
All rights reserved.


