=== OmniGEO Ai SEO ===
Contributors: deepwp24
Tags: ai seo, geo, aeo, ai visibility, answer engine optimization
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find what stops search engines and AI assistants from quoting your site, fix it with guided tasks, and track AI visibility.

== Description ==

People now ask ChatGPT, Perplexity, Gemini, Claude and Google AI Overviews instead of typing into a search box, and those tools quote a small set of pages. OmniGEO checks your WordPress site for search engines and AI answer engines, explains every problem and opportunity in plain language, proposes safe fixes, and confirms when they are fixed.

It is built around one simple loop: **scan → fix the most valuable task → re-check → see your progress**.

Everything core runs locally on your site and is free. Optional AI features use your own API key from OpenAI, Anthropic, Perplexity, Google Gemini, OpenRouter, Mistral, DeepSeek or any OpenAI-compatible endpoint (including a local Ollama or LM Studio server), with a hard monthly limit you control.

= Growth Center and Action Center =

* **Site-wide scan** of posts, pages, products and custom post types, read straight from WordPress in small batches, so it is safe on shared hosting. Unchanged pages are skipped on re-scans. A light background scan runs weekly (can be turned off).
* **Action Center**: every issue grouped into clear tasks, ordered by impact (severity × page importance), each with why it matters, how to fix it, the affected pages and a Re-check button that confirms the fix.
* **Page importance** is calculated for you: front page, menu links, internal links, AI bot reads, sales, freshness, plus a star to mark your money pages.
* **Internal link opportunities**: orphan pages and pages without internal links, with suggested related pages.
* **Duplicate titles, meta descriptions and product descriptions** across the site.
* **Mark as intentional** for deliberate choices, so they stop counting against your score.
* **Score history, recent wins** and an optional **weekly email** report.

= Answer Optimizer (in the editor) =

* Detects the main question a page should answer, from the title or focus keyword.
* Checks whether the opening actually answers it, and flags filler openings such as “In this article…”.
* Finds question headings without a direct answer, comparison pages without a table and how-to pages without numbered steps.
* Turns answered question headings into **FAQ schema** with one click.
* Optional: **drafts a 40–60 word direct answer** from your own text with your AI key, for you to review and insert. Revisions keep your previous version.
* Live **answer-readiness score** while you write, plus an AI Score column on your lists.

= One-click internal links =

* For orphan pages and pages without internal links, OmniGEO finds sentences on your site that already mention the topic and shows them with the phrase highlighted.
* Tick the ones you like and insert the links in one click. Only visible text is changed: never existing links, headings, code, block settings or shortcodes.
* Every change can be undone, and WordPress revisions keep each version.

= Local business schema =

* Choose from 30 business types (dentist, restaurant, law firm, plumber, hotel, store…) and add address, phone, opening hours, service area, price range, map link and coordinates.
* Output as LocalBusiness schema, and as a separate homepage node when Yoast, Rank Math, SEOPress or All in One SEO output the main graph.
* The scan tells you when address, phone or hours are missing.

= WooCommerce Catalog Copilot =

* Checks every product: short and full description, specifications, images and alt text, category, price, GTIN/EAN and copied descriptions.
* **Bulk fixes with preview, approval and undo**: alt text from product name, short description from the full description, or AI-drafted summaries for up to 10 selected products at a time. Existing values are never overwritten.
* Adds your brand to WooCommerce Product schema when it has none, and lets WooCommerce own Product schema to avoid duplicates.

= AI Visibility monitoring =

* Save the questions buyers ask, other spellings of your brand (abbreviations, local-language or other-script versions) and up to 10 competitors.
* Each check stores the full answers and measures **mentions, recommendations, citations, position and share of voice**, compared with the previous check.
* Shows the **sources assistants cited** and labels them as yours, a competitor or an opportunity.
* Questions where competitors appear and you do not become tasks, with a one-click **draft page** outline.
* **Brand facts accuracy check**: enter your official phone numbers, emails, domains, founding year and statements that are outdated. Every answer that mentions you is checked, and wrong details (an old phone number, a lookalike domain, a wrong founding year) become a high-priority task. Create a “key facts” page draft from your facts in one click.
* Answers from providers without live web search are labelled as model knowledge, not web citations.
* Brand matching ignores letter case, accents, spacing and hyphens, and works with non-Latin scripts.

= AI Traffic (Bot & Referral Intelligence) =

* Three separate layers: bots that claim to be AI bots, **verified bots** (optional reverse-DNS and published IP-range checks), and **visitors from AI answers** (Referer or utm_source such as chatgpt.com).
* Bots are grouped by purpose: reading pages for a user right now, AI search indexing, or model training.
* Lists the pages assistants fetched for users and the **missing pages (404) AI bots requested**.
* Raw logs are kept for a period you choose, then summarised into daily totals.
* **CDN and firewall check**: OmniGEO requests your homepage as a browser and as AI search crawlers. If Cloudflare, Sucuri, Imperva, CloudFront, Akamai or another edge network blocks or challenges the crawlers that your robots.txt allows, you get a task with where to change it. It also warns when no AI search crawler has visited for three weeks.
* Ready-made GA4 channel regex.

= Technical and schema =

* Organization, WebSite, Person and Article schema linked with @id and sameAs; FAQ and Product on demand. Steps aside when Yoast, Rank Math, SEOPress or All in One SEO already output a graph, and never prints a second FAQ when an SEO plugin FAQ block is present.
* Reads focus keywords, titles, descriptions, noindex and canonical settings from Yoast, Rank Math, SEOPress and All in One SEO.
* robots.txt modes: allow all, **allow AI search but block AI training**, or block all AI crawlers. Never loosens a site that discourages search engines.
* Optional meta description fallback when no SEO plugin is active.
* URL audit of any page's live HTML, site health checks, IndexNow (manual or automatic on publish) and an optional llms.txt.

= Who it is for =

Bloggers, publishers, agencies, shop owners and anyone who wants to be the source an AI quotes. It pairs well with classic SEO work, since answer engine optimization and generative engine optimization build on the same foundation.

== External Services ==

This plugin connects to third-party services only when you turn on or use the related feature. Nothing is sent in the background unless you enable a schedule or option listed here.

AI features (optional, off until you add your own API key): AI visibility checks, direct-answer drafts and product summaries send the question or the page/product text you chose to the provider you select. Scheduled visibility checks run only if you enable them, and every request counts against the monthly limit you set.

* OpenAI: https://api.openai.com. Terms: https://openai.com/policies/terms-of-use Privacy: https://openai.com/policies/privacy-policy
* Google Gemini: https://generativelanguage.googleapis.com. Terms: https://ai.google.dev/gemini-api/terms Privacy: https://policies.google.com/privacy
* OpenRouter: https://openrouter.ai. Terms: https://openrouter.ai/terms Privacy: https://openrouter.ai/privacy
* Mistral AI: https://api.mistral.ai. Terms: https://mistral.ai/terms Privacy: https://mistral.ai/terms#privacy-policy
* DeepSeek: https://api.deepseek.com. Terms: https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html Privacy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html
* Any other endpoint you enter yourself (for example your company gateway or a local model server): requests go only to the URL you provide.
* Anthropic: https://api.anthropic.com. Terms: https://www.anthropic.com/legal/consumer-terms Privacy: https://www.anthropic.com/legal/privacy
* Perplexity: https://api.perplexity.ai. Terms: https://www.perplexity.ai/hub/legal/terms-of-service Privacy: https://www.perplexity.ai/hub/legal/privacy-policy

Bot identity verification (optional, off by default): when enabled, the plugin downloads the public crawler IP lists published by OpenAI (https://openai.com/gptbot.json, https://openai.com/searchbot.json, https://openai.com/chatgpt-user.json) and Perplexity (https://www.perplexity.ai/perplexitybot.json, https://www.perplexity.ai/perplexity-user.json) at most once a day, and performs reverse DNS lookups of bot IP addresses through your server's resolver. No data about your site or visitors is sent.

CDN check (local): the CDN and firewall check requests your own homepage from your server a few times with AI crawler user agents (cached for 12 hours). No third party is contacted.

IndexNow (optional): when you submit a URL, or when you enable automatic pings, the plugin sends that URL and your key to https://api.indexnow.org so participating search engines learn about the change. More info: https://www.indexnow.org Microsoft privacy statement: https://privacy.microsoft.com/privacystatement

= Data the plugin stores =

* AI bot requests: URL, user agent, status and (for bots only) IP address, kept for the retention period you set (90 days by default), then summarised into daily totals.
* Visits from AI products: URL, referrer and user agent. No IP address and no cookies.
* Scan findings, AI visibility answers and settings, in your own database. Everything is removed when you delete the plugin.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install it from the Plugins screen.
2. Activate it.
3. Open OmniGEO Ai SEO → Settings and fill in your brand details.
4. On the Growth Center, click Scan site, then work through the Action Center.
5. Open any post or product to use the Answer Optimizer while you write.

== Frequently Asked Questions ==

= What is the difference between SEO, GEO and AEO? =
SEO helps you rank in classic search results. GEO (generative engine optimization) and AEO (answer engine optimization) help AI tools understand and quote your content. They overlap a lot, and this plugin focuses on the AI side while staying friendly to your normal SEO setup.

= Will this make ChatGPT or Perplexity cite my site? =
Nothing can guarantee that. What this plugin does is find and help you remove the technical and structural blockers, so your content is easy to read, trust and quote, and then measure what changes. The rest is good content and a real, findable brand.

= Is the scan heavy for my hosting? =
No. It reads content from WordPress directly instead of crawling your site over HTTP, processes a dozen pages per request, and skips pages that did not change since the last scan. You can turn the weekly background scan off in Settings.

= Does llms.txt help me appear in Google AI Overviews? =
Google says no special files or AI-specific markup are needed for AI Overviews and AI Mode; normal SEO fundamentals apply. OmniGEO keeps llms.txt as an optional extra for tools that read it, and does not treat it as a ranking factor.

= Which AI providers can I use? =
OpenAI, Anthropic, Perplexity, Google Gemini, OpenRouter, Mistral, DeepSeek, or any OpenAI-compatible endpoint such as a company gateway or a local Ollama / LM Studio server. AI is optional: scanning, the Action Center, internal links, schema and bot tracking work without it.

= My robots.txt allows AI bots. Why don't they visit? =
Often a CDN or firewall (for example Cloudflare's AI bot blocking or Bot Fight Mode) stops them before WordPress sees the request. OmniGEO's CDN check tests this and tells you where to change it.

= Does it work with Yoast SEO and Rank Math? =
Yes. It reads your focus keyword from Yoast, Rank Math, SEOPress or All in One SEO, and it steps aside on schema so you never get a duplicate entity graph.

= Does it support WooCommerce? =
Yes. On product pages it lets WooCommerce own the Product schema to avoid conflicts, and it adds product specific extractability checks.

= Does it cost money or need a cloud account? =
No. Everything runs on your own site. The only outside calls are the optional AI features, which use your own API key and a monthly limit you set, the optional bot verification, and IndexNow.

= Does it work for content that is not in English? =
Yes. Word counts and brand matching work across languages and scripts, including accented Latin languages, right-to-left languages such as Arabic and Hebrew, and Chinese and Japanese, which are written without spaces. Questions are recognised by their question mark in any language, and by question words in several languages. The admin supports right-to-left layouts, and the interface is fully translatable.

== Screenshots ==

1. Live Content Extractability score in the post editor.
2. AI Score column on the Posts list.
3. AI Traffic dashboard with fetch versus crawl and the GA4 setup.
4. GEO and AEO audit results for a single page.
5. AI visibility check using your own API key.
6. Settings with brand profile and entity details.

== Changelog ==

= 1.2.1 =
Important stability fix, please update:
* Brand matching now ignores Latin accents (Café = Cafe), and word counts are correct for Chinese and Japanese.
* Suggested AI visibility questions and the draft page outline are translatable and follow your site language.
* The database upgrade no longer runs during visitors' page loads. On sites with a large AI-bot log from 1.0, the upgrade could lock the table while every visitor tried to run it at the same time, making the site unresponsive. It now runs once, in the admin or cron, behind a lock, and old rows are updated in small background batches.
* Bot logging pauses until the upgrade is done.
* Every feature that runs on the public site or in cron is now isolated: if one fails, it is skipped and logged, and the site keeps working. Administrators see a notice with the error to report.

= 1.2.0 =
New:
* Any AI provider: Google Gemini, OpenRouter, Mistral, DeepSeek and any OpenAI-compatible endpoint (company gateways, Groq, Together, local Ollama or LM Studio), plus a Test connection button. OpenAI requests now use max_completion_tokens so newer models work.
* One-click internal links for orphan pages and pages without internal links: highlighted preview, insert selected, undo.
* CDN and firewall check that detects when Cloudflare or another edge network blocks AI search crawlers that robots.txt allows, with provider-specific advice. New tasks: "Your CDN or firewall blocks AI crawlers" and "No AI search crawler has visited in 3 weeks".
* LocalBusiness schema with 30 business types, address, phone, email, opening hours, service area, price range, map link and coordinates, plus an incomplete-profile task.
* Brand facts accuracy check in AI Visibility: wrong phone numbers, emails, lookalike domains, founding years and outdated statements in AI answers become tasks. One-click “key facts” page draft.

= 1.1.0 =
New:
* Growth Center dashboard with answer-readiness score, trend, today's top tasks, recent wins and a getting-started checklist.
* Site-wide scanner (batched, cached by content hash, weekly background run) with a versioned rule engine.
* Action Center: prioritised tasks with explanations, affected pages, link suggestions, Re-check to verify fixes, mark as intentional, and important-page flag.
* Answer Optimizer in the editor: main question, direct-answer check, filler-opening detection, unanswered question headings, one-click FAQ schema, optional AI draft of a direct answer.
* WooCommerce Catalog Copilot: product checks and bulk fixes with preview, approval and undo.
* AI Visibility monitoring: tracked questions, brand aliases, competitors, run history, mentions, recommendations, citations, position, share of voice, cited sources and visibility gaps as tasks.
* Bot & Referral Intelligence: bot purpose classes, optional identity verification, 404s requested by AI bots, utm_source detection, log retention with daily roll-ups.
* robots.txt mode "Allow AI search, block AI training".
* Optional meta description fallback, automatic IndexNow pings and weekly email report.
* Multilingual text handling (non-Latin scripts and digits) and right-to-left admin styles.

Fixes and hardening:
* robots.txt: the plugin no longer adds "Allow: /" groups for AI bots. Those groups overrode your default rules (such as Disallow: /wp-admin/), and on sites set to discourage search engines they re-opened the site to AI bots. "Block all" no longer blocks Bing and Apple search.
* URL audit: redirects are re-validated against private addresses, response size is limited, word counts are correct for non-Latin text, and FAQ/Speakable markup is reported as optional instead of failing. New checks: noindex (meta and header), canonical and AI search crawler access.
* The saved API key is no longer printed into the settings page; it can be removed or set in wp-config.php (AIGS_AI_API_KEY). Provider values are validated. AI requests have a monthly limit.
* Anthropic requests use a current model and read the text block of the reply correctly.
* FAQ schema is skipped when a Yoast or Rank Math FAQ block already outputs it. HowTo nodes without steps are no longer printed.
* Fixed a stray translator comment that was printed on the Settings page.
* Database schema upgrades now run automatically after an update; scheduled jobs are removed on deactivation and everything is removed on uninstall.

= 1.0.0 =
First public release. Includes:
* Live Content Extractability analyzer in the editor, with an AI Score column on Posts and Products. Reads non Latin content, including Persian, Arabic and CJK.
* GEO and AEO audit with a 0 to 100 score and fixable advice.
* Schema and JSON-LD: Organization, Person and Author, Article, FAQ, HowTo, Product and Speakable, linked with sameAs and @id.
* AI bot and referral tracking that splits user triggered fetches from index crawls, plus an AI Traffic page with a ready made GA4 setup.
* Site health checks and IndexNow submission.
* llms.txt manager and AI crawler access rules.
* Optional AI visibility check using your own OpenAI, Anthropic or Perplexity key.
* Yoast SEO and Rank Math integration with schema de duplication, and WooCommerce support with product specific checks.

== Upgrade Notice ==

= 1.2.1 =
Stability fix: prevents the site from becoming unresponsive while the database upgrades after updating from 1.0/1.1. Update recommended.

= 1.2.0 =
Adds one-click internal links, LocalBusiness schema, a CDN/firewall check for AI crawlers, brand fact accuracy checks and support for Gemini, OpenRouter, Mistral, DeepSeek and any OpenAI-compatible or local model.

= 1.1.0 =
Big update: site-wide scan, Action Center, Answer Optimizer, WooCommerce bulk fixes and AI visibility monitoring. Also fixes robots.txt rules that could override your defaults. Run your first scan from the new Growth Center.

= 1.0.0 =
First public release.
