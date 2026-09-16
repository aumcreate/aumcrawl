=== AumCrawl – AI Crawler Control: See and Block AI Bots ===
Contributors: aumcreate
Tags: ai crawler, block ai bots, gptbot, robots.txt, crawler log
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See which AI crawlers read your site — GPTBot, ClaudeBot, PerplexityBot and more — verify they are who they claim, and block the ones you choose.

== Description ==

Every publisher is now asking the same question and nobody can answer it: **is ChatGPT actually reading my site?**

This plugin answers it. It records every known crawler that reaches your site - AI, search engine, SEO tool, link preview - and shows you who came, when, and which pages they read. Then it lets you turn away the ones that give you nothing in return.

= AI crawlers are not one thing =

Most "block AI" plugins block everything, and that is the wrong move. AI crawlers split into two groups that behave in opposite ways.

**Some send traffic back.** OAI-SearchBot, PerplexityBot, Claude-SearchBot and Google-Extended fetch a page because a person asked a question, and the answer credits you with a link. Blocking them costs you visits.

**Some take and give nothing.** GPTBot, ClaudeBot, CCBot, Bytespider and the SEO backlink crawlers pull your content into a training corpus or a commercial dataset. No link, no citation, no visitor.

The plugin sorts every crawler into those groups and puts the switch next to the evidence, so you are deciding with numbers in front of you rather than in the abstract.

= What it will not let you do =

There is deliberately no switch for Googlebot, Bingbot, or the crawlers that build link previews on Facebook, X and LinkedIn. Turning one of those off does real damage, and that belongs in your own robots.txt rather than behind a button you can hit by accident. They are recorded, so you can still see when Google last came by.

= Honest about what each control does =

* **Rules in robots.txt** - honoured by every crawler listed in this plugin, and ignored by anything that chooses to. It is a request, not a fence.
* **Turning blocked crawlers away** - the strongest option. The page is never served. Only works against crawlers that say who they are.
* **A "do not train on this" note** - the `X-Robots-Tag: noai` convention. Weak. Some tools read it, many do not. It costs nothing, so it is there, labelled for what it is.

The settings screen shows which of these is genuinely in effect right now. It does not assume a filter worked: it fetches your live robots.txt and looks. If another plugin is replacing the file, or a robots.txt exists on disk, you are told so, told which plugin is responsible, and given the exact lines to paste instead.

= Are they really who they say they are? =

Anyone can send `User-Agent: GPTBot`. The plugin checks the claim against the hostname each operator publishes, using forward-confirmed reverse DNS, and shows you which visits could not be verified.

**No IP addresses are stored.** The address is used inside that one request and then discarded. If a check fails, only a shortened network range is kept - the last part of the address is removed first - which is enough to see that a range is impersonating a crawler without ever recording who connected.

= Works alongside your SEO plugin =

robots.txt is crowded, so this plugin adds to it rather than taking it over.

* **Yoast SEO** - rules are appended after Yoast's, never replacing them
* **Rank Math** - same, and if Rank Math is set to replace robots.txt entirely you are told, and given the block to paste into its editor
* **All in One SEO** - same
* **SEOPress, Slim SEO, Squirrly, Better Robots.txt** - appended
* If any plugin already writes a rule for a crawler, this one stands aside and says so rather than writing a second, conflicting rule

It does not generate llms.txt. That is a different job, and other plugins already do it.

= Crawlers covered =

Sends traffic back: OAI-SearchBot, ChatGPT-User, PerplexityBot, Perplexity-User, Claude-User, Claude-SearchBot, Google-Extended.

Takes and gives nothing back: GPTBot, ClaudeBot, CCBot, Bytespider, Applebot-Extended, Meta-ExternalAgent, Amazonbot, Diffbot, omgilibot, AhrefsBot, SemrushBot, MJ12bot, DotBot, DataForSeoBot.

Recorded only: Googlebot, Bingbot, DuckDuckBot, Applebot, facebookexternalhit, Twitterbot, LinkedInBot.

New crawlers appear faster than plugin releases, so the list is filterable. See the FAQ.

= No account, no service, no cost =

Nothing is sent anywhere. There is no API key to obtain, no external service, and no paid tier. The plugin reads your own traffic and writes its own robots.txt rules.

= Source code =

The released source is on GitHub at https://github.com/aumcreate/aumcrawl — bug reports and pull requests are welcome there.

== Installation ==

1. Install and activate.
2. Go to **Settings → AI Crawlers**.
3. Leave it for a day or two, then come back and see who visited.

Nothing is blocked until you switch something off, so activating the plugin changes nothing about how your site is crawled.

== Frequently Asked Questions ==

= Will this stop my content being used for AI training? =

It will stop the crawlers that honour robots.txt, which includes the large, named ones. It cannot stop software that ignores robots.txt and hides what it is. For that you need blocking above WordPress, at your CDN or web server. Any plugin claiming otherwise is overstating what it can do.

= Should I block GPTBot? =

That is your call, and the numbers on the settings screen are there to inform it. GPTBot collects training data and sends nothing back. OAI-SearchBot is separate: it powers ChatGPT search, whose answers link to you. Blocking one does not block the other, which is exactly why they are listed separately.

= Why can I not block Googlebot? =

Because a plugin should not offer a one-click way to remove your site from Google. If you genuinely want that, write it into your own robots.txt where the decision is deliberate.

= The counts look low. =

If you run a page cache, requests answered from the cache never reach PHP and so cannot be counted. The settings screen names the cache plugin it detects and says the totals are a lower bound. Your server or CDN logs hold the complete picture.

= Everything shows "could not be verified". =

Your site is probably behind a CDN or a reverse proxy, so the connecting address belongs to that hop rather than to the crawler. The settings screen flags this. Pass the real visitor address through with the `aumcrawl_client_ip` filter.

= How do I add a crawler that is not listed? =

Use the `aumcrawl_bots` filter to add an entry with its User-Agent needle, its robots.txt token, and the group it belongs in.

= Does it store personal data? =

No. Crawler names, paths and counts are recorded. IP addresses are not stored at any point, and the only address-derived value kept is a shortened network range for visits that failed the identity check.

== Screenshots ==

1. Who read your site, and what is actually in effect right now
2. Crawlers that send traffic back, with the switch beside the visit count
3. Crawlers that take and give nothing back
4. Search engines and link previews: recorded, deliberately not switchable
5. Settings, and the pages crawlers read most

== Changelog ==

= 1.0.2 =
* Tags and summary now name what people search for. No functional change to the plugin.
* A line at the foot of the settings screen linking to the rest of the AumCreate ecosystem.

= 1.0.1 =
* Renamed for the plugin directory so the listing title names both halves of the plugin: seeing which AI crawlers read the site, and blocking the ones you choose. No functional change.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First release.
