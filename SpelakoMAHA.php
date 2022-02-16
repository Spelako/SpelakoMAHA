<?php
/*
 * Copyright (C) 2020-2022 Spelako Project
 * 
 * This file is part of SpelakoMAHA.
 * Permission is granted to use, modify and/or distribute this program 
 * under the terms of the GNU Affero General Public License version 3.
 * You should have received a copy of the license along with this program.
 * If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
 * 
 * 此文件是 SpelakoMAHA 的一部分.
 * 在 GNU Affero 通用公共许可证第三版的约束下,
 * 你有权使用, 修改, 复制和/或传播该软件.
 * 你理当随同本程序获得了此许可证的副本.
 * 如果没有, 请查阅 <https://www.gnu.org/licenses/agpl-3.0.html>.
 * 
 */

function _log($msg) {
	echo('['.date('Y-m-d H:m:s').'] '.$msg.PHP_EOL);
}

function post($url, $content) {
	$result = file_get_contents($url, false, stream_context_create(array(
		'http' => array(
			'method' => 'POST',
			'header' => 'Content-type: application/json',
			'content' => $content,
			'timeout' => 15
		)
	)));
	return $result;
}

$cliargs = getopt('', ['core:', 'config:', 'host:', 'verify-key:', 'qq:']);

if(!(isset($cliargs['core']) && file_exists($cliargs['core']))) {
	exit('提供的 SpelakoCore 路径无效. 请使用命令行参数 "--core" 指向正确的 SpelakoCore.php.');
}

if(!(isset($cliargs['config']) && file_exists($cliargs['config']))) {
	exit('提供的配置文件路径无效. 请使用命令行参数 "--config" 指向正确的 config.json.');
}

if(empty($cliargs['host']) || empty($cliargs['verify-key']) || empty($cliargs['qq'])) {
	exit('未指定 host, verify-key 或 qq. 请使用命令行参数 "--host", "--verify-key" 或 "--qq" 指定正确的值.');
}

require_once(realpath($cliargs['core']));
$core = new SpelakoCore(realpath($cliargs['config']));

echo SpelakoUtils::buildString([
	'Copyright (C) 2020-2022 Spelako Project',
	'This is program licensed under the GNU Affero General Public License version 3 (AGPLv3).'
]).PHP_EOL;

while(true) {
	_log('正在连接至 mirai-api-http...');

	$verifyResult = json_decode(post($cliargs['host'].'/verify', json_encode([
		'verifyKey' => $cliargs['verify-key']
	])));
	if($verifyResult->code !== 0) {
		_log('验证失败! (状态码: '.$verifyResult->code.')');
		_log('将在 10 秒后重新连接...');
		sleep(10);
		continue;
	}

	$bindResult = json_decode(post($cliargs['host'].'/bind', json_encode([
		'sessionKey' => $verifyResult->session,
		'qq' => $cliargs['qq']
	])));
	if($bindResult->code !== 0) {
		_log('绑定失败! (状态码: '.$bindResult->code.')');
		_log('将在 10 秒后重新连接...');
		sleep(10);
		continue;
	}

	_log('连接成功! 开始监听请求... (Session key: '.$verifyResult->session.')');
	while(true) {
		$fetchResult = json_decode(file_get_contents($cliargs['host'].'/fetchLatestMessage?count=20&sessionKey='.$verifyResult->session));
		if($fetchResult->code !== 0) {
			_log('连接断开! (状态码: '.$fetchResult->code.')');
			_log('将在 3 秒后重新连接...');
			sleep(3);
			break;
		}
		if($fetchResult->data) foreach($fetchResult->data as $v) {
			if($v->type == 'GroupMessage'
			&& $v->messageChain[1]->type == 'Plain'
			&& $v->messageChain[1]->text[0] == '/') {
				_log(SpelakoUtils::buildString(
					'群: %1$s | 用户: %2$s | 消息: %3$s',
					[
						$v->sender->group->id,
						$v->sender->id,
						$v->messageChain[1]->text
					]
				));
				$requestResult = $core->execute($v->messageChain[1]->text, $v->sender->id);
				if(!$requestResult) {
					$cmd = explode(' ', $v->messageChain[1]->text)[0];
					foreach($core->getCommands() as $pointer) foreach($pointer->getName() as $pointerCmd) {
						similar_text($cmd, $pointerCmd, $percent);
						if($percent > 70) {
							$requestResult = SpelakoUtils::buildString([
								'你可能想输入此命令: %1$s',
								'但是你输入的是: %2$s'
							], [
								$pointerCmd,
								$cmd
							]);
							break 2;
						}
					}
				}
				$sendResult = post($cliargs['host'].'/sendGroupMessage', json_encode([
					'sessionKey' => $verifyResult->session,
					'target' => $v->sender->group->id,
					'messageChain' => [[
						'type' => 'Plain',
						'text' => $requestResult
					]]
				]));
			}
		}
		sleep(1);
	}
}
?>
