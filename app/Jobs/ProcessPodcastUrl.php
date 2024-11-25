<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\ListeningParty;
use App\Models\Podcast;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;
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
        $episodeMediaUrl = (string)$latestEpisode->enclosure['url'];

        $namespaces = $xml->getNamespaces(true);
        $itunesNamespace = $namespaces['itunes'] ?? null;

        $episodeLength = null;

        if ($itunesNamespace) {
            $episodeLength = $latestEpisode->children($itunesNamespace)->duration;
        }

        if (empty($episodeLength)) {
            $fileSize = (int) $latestEpisode->enclosure['length'];
            $bitrate = 128000;
            $durationInSeconds = ceil($fileSize * 8 / $bitrate);
            $episodeLength = (string) $durationInSeconds;
        }

        try {
            if (str_contains($episodeLength, ':')) {
                // Duration is in HH:MM:SS or MM:SS format
                $parts = explode(':', $episodeLength);
                if (count($parts) == 2) {
                    $interval = CarbonInterval::createFromFormat('i:s', $episodeLength);
                } elseif (count($parts) == 3) {
                    $interval = CarbonInterval::createFromFormat('H:i:s', $episodeLength);
                } else {
                    throw new Exception('Unexpected duration format');
                }
            } else {
                // Duration is in seconds
                $interval = CarbonInterval::seconds((int) $episodeLength);
            }
        } catch (Exception $e) {
            Log::error('Error parsing episode duration: '.$e->getMessage());
            $interval = CarbonInterval::hour(); // Default to 1 hour if parsing fails
        }

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
