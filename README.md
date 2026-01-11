# 🛡️ FluentCart Invisible CAPTCHA Pro (v3.0)

**FluentCart Invisible CAPTCHA Pro** is an advanced security solution specifically engineered for **FluentCart** and **Fluent Forms**. It seamlessly integrates Google reCAPTCHA v3 to protect your checkout flow from bots, spam, and fraudulent orders—all without adding any friction for your real customers.

---

## ✨ Core Features

* **🕵️ Invisible Protection**: Runs silently in the background. No puzzles or checkboxes, keeping the user experience 100% smooth.
* **🧠 Behavioral Analysis**: Uses Google's v3 scoring system to detect bots based on mouse movements, timing, and interaction patterns.
* **🌍 Threat Intelligence Dashboard**: Features a live world map visualizing bot attack origins in real-time.
* **🚫 VPN & Proxy Detection**: Automatically identifies and flags users hiding behind VPNs or data center proxies with visual "VPN Detected" badges.
* **🔐 Automated Blacklisting**: Smart logic that automatically bans IP addresses after multiple suspicious attempts (Score < 0.5).
* **📊 Security Analytics**: Provides a 7-day visual report comparing verified human traffic vs. blocked bot attempts.
* **🚨 Real-time Toaster Alerts**: Integrated with SweetAlert2 to show beautiful, instant notifications for blocked submissions.

---

## 🛠️ Installation

1.  Upload the plugin folder to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the **Plugins** menu in WordPress.
3.  Navigate to **FC Captcha > Settings** and enter your Google reCAPTCHA v3 **Site Key** and **Secret Key**.

---

## 📊 Monitoring & Logs

Navigate to **FC Captcha > CAPTCHA Logs** to access the security nerve center:
* **IP Address & Timestamp**: Detailed tracking of every interaction.
* **Risk Score**: Probability scores ranging from 0.0 (Bot) to 1.0 (Human).
* **Location Insights**: View the country of origin and network type (Residential vs. VPN/Hosting) for every visitor.

---

## 💻 Tech Stack
- **Backend:** PHP, WordPress Plugin API
- **Security:** Google reCAPTCHA v3, IP-API Enterprise Integration
- **Frontend:** JavaScript (AJAX Interceptors), SweetAlert2, Chart.js, jsVectorMap

---
*Developed with a focus on Enterprise Security & Conversion Rate Optimization.*
