# B2BKing ERP Sync (JSON Importer)

**Note:** This plugin is shared publicly for portfolio and demonstration purposes only. It is a proprietary solution. Redistribution or commercial use is prohibited without explicit permission from the author.

## Description

B2BKing ERP Sync is a custom WordPress plugin that enables automatic integration between ERPs (such as PHC) and the B2BKing plugin for WooCommerce.

It works by exposing a REST API endpoint which accepts JSON data that defines business rules like pricing, discounts, and customer-specific conditions.

## Features

- Group-based pricing per SKU
- Percentage discounts per user and product
- Fixed pricing per user
- Priority-based rule handling
- JSON-based input compatible with PHC and other ERP systems

## Installation

1. Upload the ZIP file through the WordPress admin panel (Plugins > Add New > Upload Plugin).
2. Activate the plugin.
3. Ensure that the B2BKing plugin is installed and active.

## Usage

Send a JSON POST request to the endpoint:
```
https://yourdomain.com/wp-json/custom/v1/import-dados-b2bking
```

Headers:
```
X-Auth-Token: your_secure_token
Content-Type: application/json
```

## JSON Example

```json
[
  {
    "RuleType": "SkuGeneralTab",
    "ForWho": "Revendedores",
    "SKU": "SYS-0015300",
    "HowMuch": "28.70"
  },
  {
    "RuleType": "Discount (Percentage)",
    "ApliesTo": "SYS-0015300",
    "ForWho": "adm_csw",
    "HowMuch": "10",
    "Priority": "1"
  },
  {
    "RuleType": "Fixed Price",
    "ApliesTo": "SYS-0015301",
    "ForWho": "cliente_xpto",
    "HowMuch": "24.99"
  }
]
```

## Security

This plugin uses token-based authentication via the 'X-Auth-Token' HTTP header. Make sure to store your token securely and rotate it periodically to maintain endpoint security.

## License

This plugin is proprietary. All rights reserved (c) 2025 José Luís.

## Contact

For licensing or integration support, please contact the author.
