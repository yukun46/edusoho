<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Service\CloudPlatform\CloudAPIFactory;

class HLSController extends BaseController
{
    public function playlistAction(Request $request, $id, $token)
    {
        $line          = $request->query->get('line', null);
        $hideBeginning = $request->query->get('hideBeginning', false);
        $returnJson    = $request->query->get('returnJson', false);
        $levelParam    = $request->query->get('level', "");
        $token         = $this->getTokenService()->verifyToken('hls.playlist', $token);
        $fromApi       = isset($token['data']['fromApi']) ? $token['data']['fromApi'] : false;

        if (empty($token)) {
            throw $this->createNotFoundException();
        }

        $dataId = is_array($token['data']) ? $token['data']['id'] : $token['data'];

        if ($dataId != $id) {
            throw $this->createNotFoundException();
        }

        $file = $this->getUploadFileService()->getFile($id);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        $streams = array();

        foreach (array('sd', 'hd', 'shd') as $level) {
            if (empty($file['metas2'][$level])) {
                continue;
            }

            if (empty($levelParam) || (!empty($levelParam) && strtolower($levelParam) == $level)) {
                $tokenFields = array(
                    'data'     => array(
                        'id'      => $file['id'].$level,
                        'fromApi' => $fromApi
                    ),
                    'times'    => $this->agentInWhiteList($request->headers->get("user-agent")) ? 0 : 1,
                    'duration' => 3600
                );

                if (!empty($token['userId'])) {
                    $tokenFields['userId'] = $token['userId'];
                }

                if (isset($token['data']['watchTimeLimit'])) {
                    $tokenFields['data']['watchTimeLimit'] = $token['data']['watchTimeLimit'];
                }

                if (isset($token['data']['hideBeginning'])) {
                    $tokenFields['data']['hideBeginning'] = $token['data']['hideBeginning'] == "true" ? true : false;
                }

                $token = $this->getTokenService()->makeToken('hls.stream', $tokenFields);
            } else {
                $token['token'] = $this->getTokenService()->makeFakeTokenString();
            }

            $params = array(
                'id'    => $file['id'],
                'level' => $level,
                'token' => $token['token']
            );

            if ($line) {
                $params['line'] = $line;
            }

            var_dump($token);
            exit();

            if (isset($token['hideBeginning'])) {
                $params['hideBeginning'] = $token['hideBeginning'] ? 0 : 1;
            } else {
                if (!$this->haveHeadLeader()) {
                    $params['hideBeginning'] = 1;
                }
            }

            $streams[$level] = $this->generateUrl('hls_stream', $params, true);
        }

        $qualities = array(
            'video' => $file['convertParams']['videoQuality'],
            'audio' => $file['convertParams']['audioQuality']
        );

        $api = CloudAPIFactory::create('leaf');

        $playlist = $api->get('/hls/playlist/json', array('streams' => $streams, 'qualities' => $qualities));
        return $this->createJsonResponse($playlist);
    }

    protected function haveHeadLeader()
    {
        $storage = $this->setting("storage");

        if (!empty($storage) && array_key_exists("video_header", $storage) && $storage["video_header"]) {
            return true;
        }

        return false;
    }

    public function streamAction(Request $request, $id, $level, $token)
    {
        $token = $this->getTokenService()->verifyToken('hls.stream', $token);
        // var_dump($token);
        // exit();

        if (empty($token)) {
            throw $this->createNotFoundException();
        }

        $dataId = is_array($token['data']) ? $token['data']['id'] : $token['data'];

        if ($dataId != ($id.$level)) {
            throw $this->createNotFoundException();
        }

        $file = $this->getUploadFileService()->getFile($id);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if (empty($file['metas2'][$level]['key'])) {
            throw $this->createNotFoundException();
        }

        $params        = array();
        $params['key'] = $file['metas2'][$level]['key'];

        if (!empty($token['data']['watchTimeLimit'])) {
            $params['trialSeconds'] = $token['data']['watchTimeLimit'];
        }

        $inWhiteList     = $this->agentInWhiteList($request->headers->get("user-agent"));
        $isBalloonPlayer = $this->setting('developer.balloon_player', 0);

        $tokenFields = array(
            'data'     => array(
                'id'            => $file['id'],
                'keyencryption' => $token['data']['fromApi'] || $inWhiteList || empty($isBalloonPlayer) ? 0 : 1
            ),
            'times'    => $inWhiteList ? 0 : 1,
            'duration' => 3600
        );

        if (!empty($token['userId'])) {
            $tokenFields['userId'] = $token['userId'];
        }

        $token = $this->getTokenService()->makeToken('hls.clef', $tokenFields);

        $params['keyUrl'] = $this->generateUrl('hls_clef', array('id' => $file['id'], 'token' => $token['token']), true);

        $hideBeginning = $request->query->get('hideBeginning');

        if (!$inWhiteList && empty($hideBeginning)) {
            $beginning = $this->getVideoBeginning($request, $level, $token['userId']);

            if ($beginning['beginningKey']) {
                $params = array_merge($params, $beginning);
            }
        }

        $line = $request->query->get('line');

        if (!empty($line)) {
            $params['line'] = $line;
        }

        $api = CloudAPIFactory::create('leaf');

        $stream = $api->get('/hls/stream', $params);

        if (empty($stream['stream'])) {
            return $this->createMessageResponse('error', '生成视频播放地址失败！');
        }

        return new Response($stream['stream'], 200, array(
            'Content-Type'        => 'application/vnd.apple.mpegurl',
            'Content-Disposition' => 'inline; filename="stream.m3u8"'
        ));
    }

    public function clefAction(Request $request, $id, $token)
    {
        $token = $this->getTokenService()->verifyToken('hls.clef', $token);

        if (empty($token)) {
            return $this->makeFakeTokenString();
        }

        if (!empty($token['userId'])) {
            if (!($this->getCurrentUser()->isLogin()
                && $this->getCurrentUser()->getId() == $token['userId'])) {
                return $this->makeFakeTokenString();
            }
        }

        $dataId = is_array($token['data']) ? $token['data']['id'] : $token['data'];

        if ($dataId != $id) {
            return $this->makeFakeTokenString();
        }

        $file = $this->getUploadFileService()->getFile($id);

        if (empty($file)) {
            return $this->makeFakeTokenString();
        }

        if (empty($file['convertParams']['hlsKey'])) {
            return $this->makeFakeTokenString();
        }

        $api = CloudAPIFactory::create('leaf');

        if (!empty($token['data']['keyencryption'])) {
            $stream = $api->get("/hls/clef/{$file['convertParams']['hlsKey']}/algo/1", array());
            return new Response($stream['key']);
        }

        $stream = $api->get("/hls/clef/{$file['convertParams']['hlsKey']}/algo/0", array());
        return new Response($stream['key']);
    }

    protected function makeFakeTokenString()
    {
        $fakeKey = $this->getTokenService()->makeFakeTokenString(16);
        return new Response($fakeKey);
    }

    protected function getUploadFileService()
    {
        return $this->getServiceKernel()->createService('File.UploadFileService');
    }

    protected function getTokenService()
    {
        return $this->getServiceKernel()->createService('User.TokenService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function agentInWhiteList($userAgent)
    {
        $whiteList = array("iPhone", "iPad", "Android");

        foreach ($whiteList as $value) {
            if (strpos(strtolower($userAgent), strtolower($value)) > -1) {
                return true;
            }
        }

        return false;
    }

    protected function getVideoBeginning(Request $request, $level, $userId = 0)
    {
        $beginning = array(
            'beginningKey'    => null,
            'beginningKeyUrl' => null
        );

        $storage = $this->getSettingService()->get("storage");

        if (!empty($storage['video_header'])) {
            $file       = $this->getUploadFileService()->getFileByTargetType('headLeader');
            $beginnings = json_decode($file['metas2'], true);
            $levels     = array($level);
            $levels     = array_merge($levels, array_diff(array('shd', 'hd', 'sd'), $levels));

            foreach ($levels as $level) {
                if (empty($beginnings[$level])) {
                    continue;
                }

                $beginning['beginningKey'] = $beginnings[$level]['key'];
                $token                     = $this->getTokenService()->makeToken('hls.clef', array(
                    'data'     => array(
                        'id' => $file['id']
                    ),
                    'times'    => $this->agentInWhiteList($request->headers->get("user-agent")) ? 0 : 1,
                    'duration' => 3600,
                    'userId'   => $userId
                ));
                $beginning['beginningKeyUrl'] = $this->generateUrl('hls_clef', array('id' => $file['id'], 'token' => $token['token']), true);
                break;
            }
        }

        return $beginning;
    }
}
