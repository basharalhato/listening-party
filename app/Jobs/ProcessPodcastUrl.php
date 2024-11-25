<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\ListeningParty;
use App\Models\Podcast;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPodcastUrl implements ShouldQueue
{
    use Queueable;

    public string $rssUrl;
    public ListeningParty $listeningParty;
    public Episode $episode;

    /**
     * Create a new job instance.
     */
    public function __construct(string $rssUrl, ListeningParty $listeningParty, Episode $episode)
    {
        $this->rssUrl = $rssUrl;
        $this->listeningParty = $listeningParty;
        $this->episode = $episode;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $xml = simplexml_load_file($this->rssUrl);

        $podcastTitle = $xml->channel->title;
        $podcastArtworkUrl = $xml->channel->image->url;

        $latestEpisode = $xml->channel->item[0];

        $episodeTitle = $latestEpisode->title;
        $episodeMediaUrl = (string) $latestEpisode->enclosure['url'];

        $namespaces = $xml->getNamespaces(true);
        $itunesNamespace = $namespaces['itunes'];

        $episodeLength = $latestEpisode->children($itunesNamespace)->duration;

        $interval = CarbonInterval::createFromFormat('H:i:s', $episodeLength);

        $endTime = $this->listeningParty->start_time->add($interval);

        $podcast = Podcast::updateOrCreate([
            'title' => $podcastTitle,
            'artwork_url' => $podcastArtworkUrl,
            'rss_url' => $this->rssUrl,
        ]);

        $this->episode->podcast()->associate($podcast);

        $this->episode->update([
            'title' => $episodeTitle,
            'media_url' => $episodeMediaUrl,
        ]);

        $this->listeningParty->update([
           'end_time' => $endTime,
        ]);
    }
}
