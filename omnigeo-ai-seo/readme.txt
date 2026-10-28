=== OmniGEO Ai SEO ===
Contributors: deepwp24
Tags: ai seo, geo, aeo, ai visibility, generative engine optimization
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI SEO toolkit for GEO and AEO. Score content for AI search, fix schema, track AI bots, and see if ChatGPT and Perplexity cite you.

== Description ==

AI search is the new SEO. People now ask ChatGPT, Perplexity, Gemini, Claude and Google AI Overviews instead of typing into a search box, and those tools quote a small set of pages. This is an AI SEO toolkit that gets your WordPress content ready for that world through Generative Engine Optimization (GEO) and Answer Engine Optimization (AEO).

The standout feature is the live Content Extractability analyzer. As you write a post or product, it scores how easily an AI can lift a clean answer from your text, and it tells you what to fix: answer first openings, semantic chunking, comparison tables, clear headings and more. It runs in real time inside the editor, and it reads non Latin content correctly, so it scores Persian, Arabic and CJK writing too.

Everything works locally and free. The only feature that calls an outside service is the optional AI visibility check, and that uses your own API key, so there is no extra subscription.

= What you get =

* Live Content Extractability score in the editor, plus an AI Score column on your Posts and Products lists so you can fix the weak pages first.
* GEO and AEO audit that gives any page a score out of 100 with plain, fixable advice.
* Schema and JSON-LD output: Organization, Person and Author, Article, FAQ, HowTo, Product and Speakable, linked together with sameAs and @id for a clean entity graph.
* AI bot and referral tracking. It detects GPTBot, ChatGPT-User, ClaudeBot, PerplexityBot, Google-Extended and more, and it splits user triggered fetches (a real person reading you inside an AI answer) from index crawls.
* AI Traffic dashboard with a ready made GA4 channel and regex so you can finally track AI clicks in Google Analytics.
* Site health checks plus IndexNow submission.
* llms.txt manager and AI crawler access rules for robots.txt.
* Live AI visibility check. Add your own OpenAI, Anthropic or Perplexity key and ask a buyer style question to see whether AI mentions your brand or links your site.
* Yoast SEO and Rank Math integration. It reads your focus keyword and avoids duplicate schema so two plugins never fight over the same graph.
* WooCommerce support. It defers to WooCommerce Product schema to prevent conflicts and adds product specific checks for the short description, specs, reviews and images.

= Who it is for =

Bloggers, publishers, agencies, shop owners and anyone who wants to be the source an AI quotes. It pairs well with classic SEO work, since answer engine optimization and generative engine optimization build on the same foundation.

== External Services ==

This plugin connects to a few third party services, and only when you choose to use the related feature.

AI visibility check (optional, off by default): when you enable it and add your own API key, the plugin sends your test prompt to the provider you pick so it can check whether your brand appears in AI answers.

* OpenAI: requests go to https://api.openai.com. Terms: https://openai.com/policies/terms-of-use Privacy: https://openai.com/policies/privacy-policy
* Anthropic: requests go to https://api.anthropic.com. Terms: https://www.anthropic.com/legal/consumer-terms Privacy: https://www.anthropic.com/legal/privacy
* Perplexity: requests go to https://api.perplexity.ai. Terms: https://www.perplexity.ai/hub/legal/terms-of-service Privacy: https://www.perplexity.ai/hub/legal/privacy-policy

IndexNow (optional): when you submit a URL, the plugin sends that URL and your key to https://api.indexnow.org so participating search engines learn about the change. IndexNow is an open protocol used by Microsoft Bing and Yandex. More info and protocol terms: https://www.indexnow.org Bing Webmaster terms: https://www.bing.com/webmasters/help/webmaster-guidelines-30fba23a Microsoft privacy statement: https://privacy.microsoft.com/privacystatement

Your test prompts and submitted URLs are only sent when you trigger these features. No data is sent in the background.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install it from the Plugins screen.
2. Activate it.
3. Open AI SEO in the admin menu and fill in your brand details under Settings.
4. Open any post or product and watch the Content Extractability box as you write.

== Frequently Asked Questions ==

= What is the difference between SEO, GEO and AEO? =
SEO helps you rank in classic search results. GEO (generative engine optimization) and AEO (answer engine optimization) help AI tools understand and quote your content. They overlap a lot, and this plugin focuses on the AI side while staying friendly to your normal SEO setup.

= Will this make ChatGPT or Perplexity cite my site? =
Nothing can guarantee that. What this plugin does is remove the technical and structural blockers, so your content is easy for AI to read, trust and quote. The rest is good content and a real, findable brand.

= Does it work with Yoast SEO and Rank Math? =
Yes. It reads your focus keyword from Yoast, Rank Math, SEOPress or All in One SEO, and it steps aside on schema so you never get a duplicate entity graph.

= Does it support WooCommerce? =
Yes. On product pages it lets WooCommerce own the Product schema to avoid conflicts, and it adds product specific extractability checks.

= Does it cost money or need a cloud account? =
No. Everything runs on your own site. The only outside calls are the optional AI visibility check, which uses your own API key, and IndexNow.

= Does it work for non English content? =
Yes. The extractability analyzer counts and reads non Latin text correctly, including Persian, Arabic and CJK.

== Screenshots ==

1. Live Content Extractability score in the post editor.
2. AI Score column on the Posts list.
3. AI Traffic dashboard with fetch versus crawl and the GA4 setup.
4. GEO and AEO audit results for a single page.
5. AI visibility check using your own API key.
6. Settings with brand profile and entity details.

== Changelog ==

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

= 1.0.0 =
First public release.
