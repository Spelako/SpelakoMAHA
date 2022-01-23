<?php
/*
 * Copyright (C) 2020-2022 Spelako Project
 * 
 * Permission is granted to use, modify and/or distribute this program under the terms of the GNU Affero General Public License version 3 (AGPLv3).
 * You should have received a copy of the license along with this program. If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
 * 
 * 在 GNU 通用公共许可证第三版 (AGPLv3) 的约束下, 你有权使用, 修改, 复制和/或传播该软件.
 * 你理当随同本程序获得了此许可证的副本. 如果没有, 请查阅 <https://www.gnu.org/licenses/agpl-3.0.html>.
 * 
 */

$cliargs = getopt('', ['core:', 'host:', 'verify-key:', 'qq:']);

if(isset($cliargs['core']) && file_exists($cliargs['core'])) {
	require_once($cliargs['core']);
	Spelako::loadCommands();
}
else {
	echo '提供的 SpelakoCore 路径无效. 请使用命令行参数 "--core" 指向正确的 SpelakoCore.php. ';
	die();
}

if(!(isset($cliargs['host']) && isset($cliargs['verify-key']) && isset($cliargs['qq']))) {
	echo '未指定 host, verify-key 或 qq. 请使用命令行参数 "--host", "--verify-key" 或 "--qq" 指定正确的值.';
	die();
}

function _log($msg) {
	echo('['.date('Y-m-d H:m:s').'] '.$msg.PHP_EOL);
}

function post($url, $content) {
	$result = file_get_contents($url, false, stream_context_create($options = array(
		'http' => array(
			'method' => 'POST',
			'header' => 'Content-type: application/json',
			'content' => $content,
			'timeout' => 15
		)
	)));
	return $result;
}

cli_set_process_title('Spelako MAHA');
echo SpelakoUtils::buildString([
	'Copyright (C) 2020-2022 Spelako Project',
	'This program licensed under the GNU Affero General Public License version 3 (AGPLv3).'
], eol: true);

while(true) {
	_log('正在连接至 mirai-api-http...');

	$verifyResult = json_decode(post($cliargs['host'].'/verify', json_encode([
		'verifyKey' => $cliargs['verify-key']
	])), true);
	if($verifyResult['code'] !== 0) {
		_log('验证失败! (状态码: '.$verifyResult['code'].')');
		_log('将在 10 秒后重新连接...');
		sleep(10);
		continue;
	}

	$bindResult = json_decode(post($cliargs['host'].'/bind', json_encode([
		'sessionKey' => $verifyResult['session'],
		'qq' => $cliargs['qq']
	])), true);
	if($bindResult['code'] !== 0) {
		_log('绑定失败! (状态码: '.$bindResult['code'].')');
		_log('将在 10 秒后重新连接...');
		sleep(10);
		continue;
	}

	_log('连接成功! 开始监听请求... (Session key: '.$verifyResult['session'].')');
	while(true) {
		$fetchResult = json_decode(file_get_contents($cliargs['host'].'/fetchLatestMessage?count=20&sessionKey='.$verifyResult['session']), true);
		if($fetchResult['code'] !== 0) {
			_log('连接断开! (状态码: '.$fetchResult['code'].')');
			_log('将在 3 秒后重新连接...');
			sleep(3);
			break;
		}
		if($fetchResult['data']) foreach($fetchResult['data'] as $v) {
			if($v['type'] == 'GroupMessage'
			&& $v['messageChain'][1]['type'] == 'Plain'
			&& $v['messageChain'][1]['text'][0] == '/') {
				_log('Request by '.$v['sender']['id'].': '.$v['messageChain'][1]['text'].PHP_EOL);
				$requestResult = Spelako::execute($v['messageChain'][1]['text'], $v['sender']['id']);
				if($requestResult) {
					$sendResult = post($cliargs['host'].'/sendGroupMessage', json_encode([
						'sessionKey' => $verifyResult['session'],
						'target' => $v['sender']['group']['id'],
						'messageChain' => [[
							'type' => 'Plain',
							'text' => $requestResult
						]]
					]));
					echo 'result: '.$sendResult;
				}
			}
		}
		sleep(1);
	}
}
