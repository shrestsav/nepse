<?php

namespace Database\Seeders;

use App\Enums\BlogContentFormat;
use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Services\Blog\BlogPostContentRenderer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BlogSamplePostsSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->first() ?? User::factory()->create([
            'name' => 'Local Blog Author',
            'email' => 'local-blog-author@example.com',
        ]);

        $renderer = app(BlogPostContentRenderer::class);

        foreach ($this->posts() as $post) {
            $blogPost = BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'user_id' => $author->id,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content_format' => $post['content_format'],
                    'body_source' => $post['body_source'],
                    'body_html' => $renderer->render($post['content_format'], $post['body_source']),
                    'source_urls' => $post['source_urls'],
                    'cover_image_path' => null,
                    'cover_image_url' => $post['cover_image_url'],
                    'status' => BlogPostStatus::Published,
                    'published_at' => $post['published_at'],
                ],
            );

            $tagIds = collect($post['tags'])
                ->map(fn (string $tag): int => BlogTag::query()->firstOrCreate(['name' => $tag])->id)
                ->all();

            $blogPost->tags()->sync($tagIds);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(): array
    {
        return [
            [
                'slug' => 'nepse-weekly-wrap-bank-led-rebound',
                'title' => 'NEPSE Weekly Wrap: Banks Lead a Late Rebound',
                'excerpt' => 'A soft start to the week turned into a bank-led recovery as traders rotated back into liquid large caps ahead of policy chatter.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## Market snapshot

NEPSE spent most of the week drifting lower before buyers returned to banking and hydropower counters in the final sessions. The rebound was not broad-based, but it was strong enough to shift sentiment from defensive to cautiously constructive.

### What traders were watching

- Banking names that had been consolidating for multiple weeks
- Liquidity returning to large-cap movers
- Whether the close above short-term resistance could hold into next week

## Why it matters

When leadership returns to banks, the broader index usually gets breathing room. That does not guarantee a trend reversal, but it often tells us institutions are willing to put risk back on the table.

## Tactical takeaway

Momentum traders will likely keep watching follow-through volume. If that fades quickly, this move may remain just a relief bounce. If it builds, the market could set up for a stronger rotation week.
MD,
                'tags' => ['nepse', 'weekly wrap', 'banking'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-banks/1200/800',
                'published_at' => Carbon::now()->subDays(1)->setTime(8, 30),
            ],
            [
                'slug' => 'hydropower-stocks-cool-after-fast-rally',
                'title' => 'Hydropower Stocks Cool Off After a Fast Three-Day Rally',
                'excerpt' => 'Hydropower names gave back part of their gains as traders locked profits, but the pullback still looks orderly rather than aggressive.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## The move

Hydropower counters that had sprinted higher earlier in the week slowed down as profit booking appeared across mid-cap names. The retracement looked more like a reset than a breakdown.

## What changed

Short-term traders who entered on the breakout started reducing size, especially in stocks that were already extended from recent bases.

### Signs to monitor next

1. Whether support zones hold on lower volume
2. Whether fresh money rotates into secondary hydropower names
3. Whether weakness spreads into the broader market

## Bottom line

This sector still has strong retail attention. For now, the key question is whether buyers return on dips or wait for a deeper washout.
MD,
                'tags' => ['hydropower', 'sector watch', 'momentum'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-hydro/1200/800',
                'published_at' => Carbon::now()->subDays(2)->setTime(7, 45),
            ],
            [
                'slug' => 'microfinance-names-attract-fresh-breakout-interest',
                'title' => 'Microfinance Names Attract Fresh Breakout Interest',
                'excerpt' => 'Select microfinance counters are back on traders’ radar as range breakouts and tighter supply create clean short-term setups.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## Market behavior

Microfinance counters were among the more active pockets of the market, with several names printing decisive moves out of recent sideways ranges.

## Why this pocket is active

The sector tends to move quickly when traders notice shrinking float and rising delivery demand. That combination can attract short-term momentum money very fast.

## Risk factor

These moves can reverse just as quickly if buyers fail to defend breakout levels. Position sizing matters more here than in large-cap banking names.

## Near-term read

If sector breadth stays healthy, microfinance could remain one of the more tradable segments in the coming sessions.
MD,
                'tags' => ['microfinance', 'breakout', 'trading setup'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-microfinance/1200/800',
                'published_at' => Carbon::now()->subDays(3)->setTime(9, 15),
            ],
            [
                'slug' => 'market-open-watch-three-levels-on-the-nepse-index',
                'title' => 'Market Open Watch: Three Levels on the NEPSE Index That Matter',
                'excerpt' => 'Heading into the open, traders are focused on one near resistance zone, one support shelf, and one breadth signal that could decide the tone.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## The setup

The index is sitting in a technically important area where both breakout traders and dip buyers have a valid case.

### Three levels to track

- The immediate resistance zone that capped the last rebound
- The short-term support area that buyers defended twice
- The breadth threshold that would confirm stronger participation

## Why it matters

When the index is compressed between obvious levels, the first hour often shapes the tone for the day. Watching breadth alongside price can reduce false reads.

## Desk view

The cleaner outcome is either a strong push through resistance with participation or a flush into support that gets bought quickly. Anything in between is likely noise.
MD,
                'tags' => ['market open', 'technical levels', 'index'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-open-watch/1200/800',
                'published_at' => Carbon::now()->subDays(4)->setTime(8, 0),
            ],
            [
                'slug' => 'insurance-sector-turns-quiet-after-strong-march-run',
                'title' => 'Insurance Sector Turns Quiet After a Strong March Run',
                'excerpt' => 'Insurance counters are moving into digestion mode, with price action suggesting a pause rather than an immediate trend failure.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## Current read

Insurance names have started to flatten out after a fast March move. Intraday pushes are being sold more quickly, and ranges are getting tighter.

## What that usually means

This kind of action often reflects a pause where prior buyers are deciding whether to hold for another leg or reduce exposure into strength.

## What would improve the chart

- Higher lows forming across multiple sessions
- A pickup in volume near support
- Relative strength versus the broader index

## Conclusion

The sector does not look broken, but it does need time. Chasing extended names here is harder to justify than waiting for cleaner continuation structures.
MD,
                'tags' => ['insurance', 'sector watch', 'swing trading'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-insurance/1200/800',
                'published_at' => Carbon::now()->subDays(5)->setTime(10, 10),
            ],
            [
                'slug' => 'why-turnover-matters-more-than-index-points-this-week',
                'title' => 'Why Turnover Matters More Than Index Points This Week',
                'excerpt' => 'A green close means less if turnover is fading. This week, liquidity has been the better signal for judging whether the bounce is real.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## Bigger picture

The index can climb on a narrow group of heavyweights, but sustainable moves usually need broader turnover support.

## What we saw

Sessions with better turnover produced cleaner closes and more stable intraday structure. Sessions with weak turnover felt fragile even when the index ended green.

## Why traders should care

Turnover tells you whether conviction is expanding or shrinking. That matters when deciding whether to press winners or stay selective.

## Working rule

If price and turnover rise together, trend continuation gets more credible. If price rises while turnover drops, caution stays warranted.
MD,
                'tags' => ['turnover', 'market breadth', 'analysis'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-turnover/1200/800',
                'published_at' => Carbon::now()->subDays(6)->setTime(6, 50),
            ],
            [
                'slug' => 'five-nepse-stocks-traders-are-watching-for-pullback-entries',
                'title' => 'Five NEPSE Stocks Traders Are Watching for Pullback Entries',
                'excerpt' => 'After recent breakouts, traders are watching for controlled pullbacks in selected leaders rather than chasing strength at the highs.',
                'content_format' => BlogContentFormat::RichText,
                'body_source' => <<<'HTML'
<h2>What traders are looking for</h2>
<p>After a string of strong sessions, the cleaner setup is no longer breakout chasing. It is identifying names that can pull back into support without damaging their trend structure.</p>
<h3>The checklist</h3>
<ul>
<li>Volume dries up on the pullback</li>
<li>Price respects the prior breakout area</li>
<li>Sector strength remains intact</li>
</ul>
<h2>Why this matters</h2>
<p>Entries taken on orderly pullbacks usually carry better risk than entries taken after a vertical move. For fast local markets, that difference matters.</p>
<p>Traders do not need every name to trigger. Two or three quality setups are enough when the market is rotating well.</p>
HTML,
                'tags' => ['watchlist', 'pullback', 'setups'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-watchlist/1200/800',
                'published_at' => Carbon::now()->subDays(7)->setTime(11, 5),
            ],
            [
                'slug' => 'does-a-rate-cut-rumor-change-the-nepse-trade',
                'title' => 'Does a Rate-Cut Rumor Change the NEPSE Trade?',
                'excerpt' => 'Policy chatter can quickly reshape leadership in the market, but traders still need price confirmation before leaning too hard into the theme.',
                'content_format' => BlogContentFormat::RichText,
                'body_source' => <<<'HTML'
<h2>The rumor cycle</h2>
<p>Any talk of easier rates tends to lift sentiment in rate-sensitive sectors first, especially banking, finance, and parts of real estate-linked sentiment trades.</p>
<h2>What to avoid</h2>
<p>Reacting to headlines without confirmation often leads to poor entries. The better approach is to watch whether leading stocks hold their gains after the initial reaction.</p>
<h3>Confirmation signals</h3>
<ol>
<li>Follow-through the next session</li>
<li>Improving turnover</li>
<li>Stronger breadth across linked sectors</li>
</ol>
<p>If those signals do not appear, the move may stay headline-driven and short-lived.</p>
HTML,
                'tags' => ['policy', 'rates', 'market sentiment'],
                'source_urls' => ['https://www.nrb.org.np/', 'https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-rates/1200/800',
                'published_at' => Carbon::now()->subDays(8)->setTime(9, 40),
            ],
            [
                'slug' => 'retail-participation-is-rising-but-so-is-chop',
                'title' => 'Retail Participation Is Rising, but So Is the Chop',
                'excerpt' => 'More names are seeing retail flows, yet intraday reversals are getting sharper, making discipline more important than ever.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## What changed

Retail participation has clearly picked up, especially in sectors with strong social chatter. That usually increases opportunity, but it also creates noisier price action.

## What that means in practice

- Breakouts can work fast
- Failed breakouts can reverse even faster
- Intraday conviction often differs from closing strength

## A practical adjustment

Traders may need tighter invalidation levels and more patience after entry. The market is offering movement, but not every move deserves a trade.

## Final thought

Choppy strength is still strength, but it rewards selectivity more than aggression.
MD,
                'tags' => ['retail traders', 'volatility', 'market behavior'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-retail/1200/800',
                'published_at' => Carbon::now()->subDays(9)->setTime(7, 20),
            ],
            [
                'slug' => 'sunday-outlook-can-the-index-hold-its-higher-low',
                'title' => 'Sunday Outlook: Can the Index Hold Its Higher Low?',
                'excerpt' => 'The next session opens with the market trying to defend a constructive higher-low structure, but breadth will need to cooperate.',
                'content_format' => BlogContentFormat::Markdown,
                'body_source' => <<<'MD'
## Opening context

The market enters Sunday with a modest technical advantage: the recent swing low is still intact. That gives bulls a framework, but not a guarantee.

## What would keep the structure healthy

1. Buyers stepping in near the first hour support band
2. Banks and hydropower avoiding sharp early distribution
3. Turnover staying respectable by midday

## What would weaken the case

A failure to defend the higher low would turn this into another range-bound reset and keep traders from pressing size.

## Outlook

The next move does not need to be explosive. It just needs to prove that buyers are willing to defend structure again.
MD,
                'tags' => ['outlook', 'sunday session', 'technical analysis'],
                'source_urls' => ['https://www.nepalstock.com/'],
                'cover_image_url' => 'https://picsum.photos/seed/nepse-sunday/1200/800',
                'published_at' => Carbon::now()->subHours(12),
            ],
        ];
    }
}
