#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use BigBlueButton\BigBlueButton;
use BigBlueButton\Parameters\GetRecordingsParameters;
use BigBlueButton\Parameters\DeleteRecordingsParameters;

/**
 * Directory where published recording files are placed
 */
$recordingsPublishDir = '/mnt/scalelite-recordings/var/bigbluebutton/published/presentation/';
/**
 * Hostname of the scalelite
 */
$url = 'https://example.com/bigbluebutton/';
/**
 * The shared secret t
 */
$secret = '123456';

$bbb = new BigBlueButton($secret, $url);

$maxAge = strtotime('-2 weeks midnight', time());

$dir = new DirectoryIterator($recordingsPublishDir);
foreach ($dir as $fileinfo) {
    if ($fileinfo->isDir() && !$fileinfo->isDot()) {
        $metaXml = $recordingsPublishDir . $fileinfo->getFilename() . '/metadata.xml';

        if (file_exists($metaXml)) {
            $xml = simplexml_load_file($metaXml);
            if ($xml) {
                $endDate = ((float)$xml->end_time / 1000);
                if ((string)$xml->state === 'published' && $maxAge > $endDate) {
                    $meetingName = (string)$xml->meetingName;
                    $meetingId = (string)$xml->meetingId;

                    $recordingParams = new GetRecordingsParameters();
                    $recordingParams->setMeetingId($meetingId);
                    $response = $bbb->getRecordings($recordingParams);

                    if ($response->getReturnCode() == 'SUCCESS') {
                        foreach ($response->getRawXml()->recordings->recording as $recording) {
                            $recordingStartDate = ((float)$recording->startTime / 1000);
                            $recordingEndDate = ((float)$recording->endTime / 1000);

                            if($maxAge > $recordingStartDate && $maxAge > $recordingEndDate) {
                                $recordingName = (string)$recording->name;
                                $deleteRecordingsParams= new DeleteRecordingsParameters((string)$recording->recordID);
                                $deleteResponse = $bbb->deleteRecordings($deleteRecordingsParams);
                                if ($deleteResponse->getReturnCode() == 'SUCCESS') {
                                    printf("%s deleted (%s - %s)\n\n",
                                        $recordingName,
                                        date('d.m.Y H:i', $recordingStartDate),
                                        date('d.m.Y H:i', $recordingEndDate)
                                    );
                                } else {
                                    echo "Error -  {$recordingName}\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}