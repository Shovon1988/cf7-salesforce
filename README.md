# cf7-salesforce
Wordpress Contact Form 7 → Salesforce Web-to-Lead integration (no API required)
# 🎯 CF7 → Salesforce Web-to-Lead  
**Send Contact Form 7 leads directly into Salesforce — without API access.**  
Perfect for **Professional Edition**, **Enterprise**, and **API-restricted orgs**.

---

## 🏷 Badges

![WordPress Plugin](https://img.shields.io/badge/WordPress-CF7-blue?logo=wordpress&logoColor=white)
![Salesforce](https://img.shields.io/badge/Salesforce-Web--to--Lead-00A1E0?logo=salesforce&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![Status](https://img.shields.io/badge/Status-Production--Ready-brightgreen)

---

# 📦 CF7 Salesforce Web-to-Lead Plugin

This plugin integrates **Contact Form 7** with **Salesforce Web-to-Lead**, allowing you to send form submissions into Salesforce **without any API**, **tokens**, or **OAuth**.

✔ Works in **Professional Edition**  
✔ Works **without API access**  
✔ No Salesforce credentials needed  
✔ 100% secure — uses Web-to-Lead POST method

---

## 🚀 Features

### 🔗 Seamless CF7 → Salesforce Integration
- Select which CF7 forms sync to Salesforce
- Map CF7 fields to Salesforce fields
- Supports both standard and custom fields

### 📝 Auto-formatted Lead Description
Your Salesforce Lead includes a clean, readable structured Description:


### 2. Activate  
In WordPress Admin:

**Plugins → CF7 Salesforce Web-to-Lead → Activate**

### 3. Configure  
Go to:

**Settings → CF7 → Salesforce Web-to-Lead**

Enter:

- **Salesforce Org ID**  
  Found in: Setup → Company Information → *Salesforce.com Organization ID*

- **Return URL**  
  The URL Salesforce redirects to after capture

- **Lead Source**  
  (optional)

- **Default Company**  
  used if none provided

- Select which CF7 forms sync  
- Add field mapping JSON

---

# 🧩 Example Field Mapping (Recommended)

```json
[
  { "cf7": "your-name", "sf": "last_name" },
  { "cf7": "your-email", "sf": "email" },
  { "cf7": "text-810", "sf": "phone" },
  { "cf7": "text-749", "sf": "company" },
  { "cf7": "your-message", "sf": "description" }
]

