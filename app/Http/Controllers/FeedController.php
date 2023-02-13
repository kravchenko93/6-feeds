<?php

namespace App\Http\Controllers;

use App\Services\FeedsSettingsService;
use App\Models\FeedXml;

class FeedController extends Controller
{
    public function getFeedPreview(string $source, string $platform)
    {
        FeedsSettingsService::check($source, $platform);

        return response(FeedsSettingsService::get($source, $platform), 200, [
            'Content-Type' => 'application/xml'
        ]);
    }

    public function getFeedStatic(string $source, string $platform)
    {
        $feedXml = FeedXml::where('source', $source)->where('platform', $platform)->orderBy('created_at', 'desc')->firstOrFail();

        return response($feedXml->xml, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }

    public function writeFeed(string $source, string $platform)
    {
        $feedXml = new FeedXml();
        $feedXml->status = FeedXml::STATUS_SUCCESS;
        $feedXml->source = $source;
        $feedXml->platform = $platform;
        $error = NULL;
        try {
            FeedsSettingsService::check($source, $platform);
            $feedXml->xml = FeedsSettingsService::get($source, $platform);
        } catch (\Exception $ex) {
            $feedXml->status = FeedXml::STATUS_ERROR;
            $error = $ex->getMessage();
        }
        $feedXml->save();

        return redirect('/feeds')->withErrors(['message' => $error]);
    }

    public function getLinks()
    {
        $systemSettings = FeedsSettingsService::getSystemSettings();
        $feedsRes = [];
        $warnings = [];
        $platforms = array_map(function (string $platformName) {
            return ['name' => $platformName, 'isActual' => true];
        }, $systemSettings->rulesSheetIds);

        $notActualPlatformNames = [];
        foreach (FeedXml::all() as $feedXml) {
            if (!in_array($feedXml->platform, $systemSettings->rulesSheetIds) && !in_array($feedXml->platform, $notActualPlatformNames)) {
                $platforms[] = [
                    'name' => $feedXml->platform,
                    'isActual' => false
                ];
                $notActualPlatformNames[] = $feedXml->platform;
            }
            if (!isset($feedsRes[$feedXml->source])) {
                $feedsRes[$feedXml->source] = [];
            }

            $feedsRes[$feedXml->source][$feedXml->platform] = [
                'source' => $feedXml->source,
                'platform' => $feedXml->platform,
                'staticLink' => $feedXml->source . '/' . $feedXml->platform,
                'staticLinkDate' => $feedXml->created_at->format('Y-m-d H:i:s'),
                'actual' => false
            ];
        }

        foreach ($systemSettings->developers as $developerSetting) {
            foreach ($developerSetting->warnings as $warning) {
                $warnings[] = $warning;
            }
            if (!isset($feedsRes[$developerSetting->name])) {
                $feedsRes[$developerSetting->name] = [
                    'actual' => true
                ];
            }
            foreach ($developerSetting->allowedFeeds as $platform) {
                if (!isset($feedsRes[$developerSetting->name][$platform])) {
                    $feedsRes[$developerSetting->name][$platform] = [];
                }
                $feedsRes[$developerSetting->name][$platform]['source'] = $developerSetting->name;
                $feedsRes[$developerSetting->name][$platform]['platform'] = $platform;
                $feedsRes[$developerSetting->name][$platform]['dynamicLink'] = $developerSetting->name. '/' . $platform;
                $feedsRes[$developerSetting->name]['actual'] = true;
            }

        }

        return view('feed_links', ['feeds' => $feedsRes, 'platforms' => $platforms, 'warnings' => $warnings]);
    }
}
