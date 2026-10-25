=== PostMag AI Chat – Website Chatbot for Claude, OpenAI & Gemini ===
Contributors: postmagthemes
Tags: chatbot, ai, chat, claude, gemini
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI chatbot that answers visitor questions using only your own posts, pages and products. Bring your own Claude, OpenAI or Gemini key.

== Description ==

PostMag AI Chat adds a friendly chat bubble to your website. Visitors ask questions in plain language, and the assistant answers using **only the content of your own site** – posts, pages, WooCommerce or Easy Digital Downloads products and anything else you choose – with links to the pages it used.

Works with the AI provider you prefer, using your own API key:

* **WordPress AI** – on WordPress 7.0+, use the AI service you already connected under Settings → Connectors. No extra key needed.
* **Anthropic Claude**
* **OpenAI**
* **Google Gemini**
* **Any OpenAI-compatible service** – OpenRouter, DeepSeek, Mistral, Groq, Together, or a model running on your own server with Ollama or LM Studio.

= Why site owners like it =

* **Answers from your content only.** The assistant is told never to invent prices, features or policies. If the answer is not on your site, it says so and points visitors to your contact page.
* **No vector database, no extra service.** Your content is indexed in your own WordPress database with fast MySQL full-text search.
* **Switch AI provider any time** without re-indexing anything.
* **Always up to date.** Content is re-indexed automatically when you publish, update or delete it.
* **Product-aware.** WooCommerce and Easy Digital Downloads prices are included automatically, and the assistant always sees your complete product list. Elementor pages are supported.
* **Cost protection.** Per-visitor hourly limit, whole-site daily limit and a maximum answer length.
* **Preview mode.** Test the assistant on your live site – only administrators see it until you switch it on.
* **Find content gaps.** The optional question log shows what visitors ask that your site does not answer yet – great ideas for FAQ entries and blog posts.
* **Lightweight.** No jQuery or frameworks on the front end; the widget loads one small script and stylesheet.
* **Private by design.** No tracking. IP addresses are only used in anonymised form for rate limiting and are never stored.
* Translation-ready, accessible keyboard-friendly widget, mobile full-screen chat, light theme that matches your accent colour.

= How it works =

1. Your content is split into short passages and stored in your database.
2. When a visitor asks a question, the plugin finds the most relevant passages.
3. Only those passages and the question are sent to the AI provider you selected, with strict instructions to answer from them.
4. The answer appears in the chat with links to the source pages.

== External services ==

This plugin connects to a third-party AI service to generate answers. **Nothing is sent until an AI service is configured and a visitor (or an administrator testing the plugin) asks a question.** Only the AI provider you select in **Settings → AI Chat** is contacted.

**What is sent, and when:** each time someone sends a chat message, the plugin sends the question, up to six previous messages from the same conversation, the assistant name, your site name and tagline, the text you entered under “Extra site info”, and short passages of relevant published content from your site. Visitor names, email addresses and IP addresses are not sent. When an administrator clicks “Test AI connection” or “Load models”, a short test message or a request for the list of available models is sent.

= WordPress AI (Settings → Connectors) =
Used when “WordPress AI” is selected (WordPress 7.0 or newer). The request is passed to WordPress core’s AI Client, which sends it to the AI service you connected under Settings → Connectors. The terms and privacy policy of that service apply (see the links below for the services most commonly used).

= Anthropic Claude =
Used when “Anthropic Claude” is selected. Requests go to `api.anthropic.com`.
Terms: https://www.anthropic.com/legal/commercial-terms
Privacy policy: https://www.anthropic.com/legal/privacy

= OpenAI =
Used when “OpenAI” is selected. Requests go to `api.openai.com`.
Terms: https://openai.com/policies/business-terms/
Privacy policy: https://openai.com/policies/privacy-policy/

= Google Gemini =
Used when “Google Gemini” is selected. Requests go to `generativelanguage.googleapis.com`.
Terms: https://ai.google.dev/gemini-api/terms
Privacy policy: https://policies.google.com/privacy

= OpenAI-compatible services =
Used when “OpenAI-compatible” is selected. Requests go to the API base URL you enter (for example OpenRouter, DeepSeek, Mistral, Groq, or your own Ollama server). The same data described above is sent to that service, and its own terms and privacy policy apply.

== Installation ==

1. Install and activate the plugin.
2. Go to **Settings → AI Chat**.
3. Choose your AI provider. On WordPress 7.0+ you can pick “WordPress AI” to reuse the service connected under Settings → Connectors; otherwise paste your API key (or add it to `wp-config.php`, see FAQ). Click **Save Changes**, then **Test AI connection**.
4. Tick the content the assistant should read and add any extra facts under **Extra site info**.
5. Click **Rebuild index**.
6. Visit your site while logged in – the chat bubble appears for administrators only. Try some questions.
7. When you are happy, tick **Enable for visitors** and save.

== Frequently Asked Questions ==

= Do I need to pay for an AI service? =

The plugin is free. You need an API key from the AI provider you choose, and you pay them directly for usage. Small, fast models usually cost a fraction of a cent per question. You can also run a free model on your own server with Ollama using the “OpenAI-compatible” option.

= Which model should I use? =

The defaults are small, fast and inexpensive models that work well for answering questions about a website. Click **Load models** to see every model your key can use.

= How do I keep my API key out of the database? =

Add one of these lines to `wp-config.php`:

`define( 'PMAIC_CLAUDE_API_KEY', 'your-key' );`
`define( 'PMAIC_OPENAI_API_KEY', 'your-key' );`
`define( 'PMAIC_GEMINI_API_KEY', 'your-key' );`
`define( 'PMAIC_COMPATIBLE_API_KEY', 'your-key' );`

= Can the assistant see private content? =

No. Only published, non-password-protected content of the types you select is indexed. You can also hide any single page using the “AI Chat” box on its edit screen.

= Does it work with page caching? =

Yes. Visitors who are not logged in do not need a security token, so cached pages work normally.

= Can I hide the chat on some pages? =

Yes, with a small code snippet:

`add_filter( 'pmaic_show_widget', function ( $show ) { return is_checkout() ? false : $show; } );`

= My site is behind Cloudflare. =

Add `add_filter( 'pmaic_trust_cloudflare', '__return_true' );` so rate limits use the real visitor address.

= Can developers add another AI provider? =

Yes. Extend the `PMAIC_Provider` class and register it with the `pmaic_providers` filter.

= Developer filters =

* `pmaic_providers` – add or remove AI providers.
* `pmaic_post_text_parts` – add extra text to a post before it is indexed.
* `pmaic_should_index` – decide whether a post is indexed.
* `pmaic_search_results` – change which passages are sent to the AI.
* `pmaic_system_prompt` – change the assistant’s instructions.
* `pmaic_stopwords` – change ignored search words.
* `pmaic_catalog_post_types` / `pmaic_catalog_line` – change which products appear in the product catalogue and how each line looks.
* `pmaic_show_widget` – show or hide the widget.
* `pmaic_http_timeout` – AI request timeout in seconds (default 45).

== Screenshots ==

1. The chat widget answering a question with links to source pages.
2. Choosing an AI provider and model.
3. Status, index and cost-protection settings.
4. Visitor questions log with “no matching content” filter.

== Changelog ==

= 1.1.0 =
* New: product catalogue – the assistant always sees the full list of products with prices, so it can answer “which themes are free?” or “list all products with prices”.
* Improved: more confident answers when the site content does contain the answer.

= 1.0.0 =
* First release: Claude, OpenAI, Gemini and OpenAI-compatible providers, automatic content indexing, cost limits, preview mode and question log.
