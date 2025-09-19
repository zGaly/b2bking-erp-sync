# B2BKing ERP Sync

Plugin WordPress para sincronização automática entre sistemas ERP (como PHC) e B2BKing via REST API.

## Funcionalidades

### Criação Automática de Entidades
- **~~Produtos WooCommerce~~** - **IMPORTANTE:** Produtos devem ser criados manualmente no WooCommerce
- **Utilizadores WordPress** - Criados com informações completas do ERP
- **Grupos B2BKing** - Criados baseados em tabelas de preços
- **Regras Dinâmicas B2BKing** - Aplicadas automaticamente

### **Sistema de Prioridades**
- **Prioridades** definem a ordem de aplicação das regras
- **Valores menores** = **maior prioridade** (executam primeiro)
- **Campo opcional** - se não especificado, usa prioridade "1"
- **Todas as regras** suportam o campo `"Priority"`

### 🔧 Tipos de Regras Suportadas
- **Group Price / SkuGeneralTab** - Preços fixos para grupos
- **Discount (Percentage)** - Descontos percentuais para utilizadores
- **Fixed Price** - Preços fixos para utilizadores específicos

### Gestão Inteligente de Utilizadores
- **Filtragem automática** - Ignora clientes inativos
- **Dados completos** - Nome, email, tipo de cliente, tabela de preços
- **Associação a grupos** - Baseada na tabela de preços do ERP
- **Retrocompatibilidade** - Funciona com formatos antigos e novos

## Instalação

1. Faça upload do plugin para `/wp-content/plugins/b2bking-erp-sync/`
2. Ative o plugin no WordPress
3. Adicione o token de API ao `wp-config.php`:

```php
define('B2BKING_API_TOKEN', 'seu_token_seguro_aqui');
```

## **IMPORTANTE - Pré-requisitos**

### **Produtos devem existir no WooCommerce**
Desde a versão 2.3, o plugin **NÃO cria produtos automaticamente**. 

**OBRIGATÓRIO:**
1. Criar produtos manualmente no WooCommerce ANTES de sincronizar
2. Garantir que os SKUs coincidem exatamente
3. Produtos devem estar publicados e visíveis

**Se o produto não existir:**
```json
{
  "status": "completed",
  "report": [
    "[0] ERROR: Product with SKU 'ABC123' does not exist. Please create the product first."
  ]
}
```

## API Endpoint

**URL:** `https://seusite.com/wp-json/custom/v1/import-dados-b2bking`  
**Método:** `POST`  
**Autenticação:** Header `X-Auth-Token`  
**Content-Type:** `application/json`

## Formatos JSON Suportados

### 1. Preço Fixo para Utilizador (com dados completos)
```json
{
  "RuleType": "Fixed Price",
  "ApliesTo": "SKU123",
  "ForWho": {
    "no": "5965",
    "nome": "Empresa XYZ",
    "email": "empresa@xyz.com",
    "inativo": false,
    "tipodesc": "Grossista",
    "tabelaPrecos": "A"
  },
  "HowMuch": "10.50",
  "Priority": "2"
}
```

### 2. Desconto Percentual para Utilizador
```json
{
  "RuleType": "Discount (Percentage)",
  "ApliesTo": "SKU456",
  "ForWho": {
    "no": "1234",
    "nome": "Cliente ABC",
    "email": "cliente@abc.com",
    "inativo": false,
    "tipodesc": "Retalhista",
    "tabelaPrecos": "B"
  },
  "HowMuch": "15.5",
  "Priority": "1"
}
```

### 3. Preço Fixo para Grupo
```json
{
  "RuleType": "GroupPrice",
  "SKU": "SKU789",
  "ForWho": "Grossistas",
  "HowMuch": "25.00",
  "Priority": "3"
}
```

### 4. Formato Múltiplo (Array)
```json
[
  {
    "RuleType": "Fixed Price",
    "ApliesTo": "SKU001",
    "ForWho": "cliente1",
    "HowMuch": "5.99",
    "Priority": "1"
  },
  {
    "RuleType": "Discount (Percentage)",
    "ApliesTo": "SKU002", 
    "ForWho": "cliente2",
    "HowMuch": "10",
    "Priority": "2"
  }
]
```

## Exemplo de Chamada cURL

```bash
curl -X POST "https://seusite.com/wp-json/custom/v1/import-dados-b2bking" \
  -H "X-Auth-Token: seu_token_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "RuleType": "Fixed Price",
    "ApliesTo": "SKU123",
    "ForWho": {
      "no": "5965",
      "nome": "Empresa XYZ",
      "email": "empresa@xyz.com",
      "inativo": false,
      "tipodesc": "Grossista",
      "tabelaPrecos": "A"
    },
    "HowMuch": "10.50",
    "Priority": "2"
  }'
```

## Exemplo de Chamada cURL

```bash
curl -X POST "https://seusite.com/wp-json/custom/v1/import-dados-b2bking" \
  -H "X-Auth-Token: seu_token_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "RuleType": "Fixed Price",
    "ApliesTo": "SKU123",
    "ForWho": {
      "no": "5965",
      "nome": "Empresa XYZ",
      "email": "empresa@xyz.com",
      "inativo": false,
      "tipodesc": "Grossista",
      "tabelaPrecos": "A"
    },
    "HowMuch": "10.50"
  }'
```

## Resposta da API

### Sucesso
```json
{
  "status": "completed",
  "report": [
    "[0] SUCCESS: Fixed price rule created for user 'Empresa XYZ' on product SKU123 = 10.5 (Priority: 2, Rule ID: 12345)"
  ]
}
```

### Erro
```json
{
  "status": "completed", 
  "report": [
    "[0] ERROR: Could not create/find user: cliente_inexistente (may be inactive)"
  ]
}
```

## Dados Armazenados

### User Meta (WordPress)
- `erp_customer_id` - Número original do cliente no ERP
- `customer_type` - Tipo de cliente (tipodesc)
- `price_table` - Tabela de preços do ERP
- `b2bking_customergroup` - ID do grupo B2BKing associado

### B2BKing Rules
- Regras dinâmicas criadas automaticamente
- Visíveis no admin do B2BKing
- Prioridades respeitadas
- Cache limpo automaticamente

## Funcionalidades Avançadas

### Filtros Automáticos
- **Clientes inativos** são ignorados automaticamente
- **Dados obrigatórios** são validados antes da criação
- **Duplicação** é evitada com verificações inteligentes

### Mapeamento de Grupos
- Tabela de preços "A" → Grupo "Tabela A"
- Utilizadores associados automaticamente
- Grupos criados sob demanda

### Logs Detalhados
- Todas as operações são registadas
- Facilita debugging e auditoria
- Visible nos logs do WordPress

## Requisitos

- WordPress 5.0+
- WooCommerce 3.0+
- B2BKing Plugin
- PHP 7.4+

## Changelog

### v2.4 ✨ **NEW FEATURE**
- **Suporte completo ao campo Priority** - Todas as regras aceitam prioridade customizada
- **Sistema de prioridades** - Controlo total sobre ordem de aplicação das regras
- **Feedback melhorado** - Mensagens de sucesso incluem informação de prioridade
- **Retrocompatibilidade** - Priority opcional (default = 1)

### v2.3 **BREAKING CHANGE**
- **REMOVIDA criação automática de produtos** - Produtos devem existir no WooCommerce antes de criar regras
- **Validação obrigatória** - API retorna erro se produto não existir
- **Maior segurança** - Evita criação acidental de produtos com dados incorretos
- **Mensagens de erro melhoradas** - Feedback claro quando produtos não existem

### v2.2
- Criação automática de utilizadores com dados completos
- Filtragem de clientes inativos
- Mapeamento automático de tabelas de preços para grupos
- Suporte para dados estruturados do ERP
- Melhor gestão de erros e validação

### v2.1
- Criação automática de produtos e grupos
- Suporte para regras de desconto percentual
- Sistema de prioridades

### v2.0
- REST API implementation
- B2BKing dynamic rules integration
- Token-based authentication

## Suporte

Para questões técnicas ou bugs, contacte o desenvolvedor ou crie uma issue no repositório.

---

**Desenvolvido por José Luís** | **© 2025** | **Todos os direitos reservados**

**Note:** This plugin is shared publicly for portfolio and demonstration purposes only. It is a proprietary solution. Redistribution or commercial use is prohibited without explicit permission from the author.

## Description

B2BKing ERP Sync is a custom WordPress plugin that enables automatic integration between ERPs (such as PHC) and the B2BKing plugin for WooCommerce.

It works by exposing a REST API endpoint that accepts JSON data to create customer-specific pricing rules, without modifying user group associations.

## Features

- Group-based pricing per SKU (`SkuGeneralTab`)
- Percentage discounts per user and product (`Discount (Percentage)`)
- Fixed pricing per user (`Fixed Price`)
- Priority-based rule handling
- JSON-based input compatible with PHC and other ERP systems
- Token-protected REST API

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
```

## Security

This plugin uses token-based authentication via the 'X-Auth-Token' HTTP header. Make sure to store your token securely and rotate it periodically to maintain endpoint security.

## License

This plugin is proprietary. All rights reserved (c) 2025 José Luís.

## Contact

For licensing or integration support, please contact the author.
