<?php
$data = [
	[
		"title" => "Тема 1: PHP основы",
		"subs" => [
			[
				"title" => "Подтема 1.1: Синтаксис и переменные",
				"content" => "PHP исполняется на сервере. Переменные начинаются со знака $. Типизация динамическая. Важны теги <?php ... ?> и базовые конструкции: if, foreach, function."
			],
			[
				"title" => "Подтема 1.2: Массивы и циклы",
				"content" => "Массивы бывают индексные и ассоциативные. Основные циклы: for, foreach, while. Для перебора массивов чаще используется foreach."
			],
			[
				"title" => "Подтема 1.3: Функции и область видимости",
				"content" => "Функции объявляются ключевым словом function. Параметры передаются по значению, по ссылке с &. Область видимости переменных локальная в функции, ключевое слово global или use для замыканий."
			],
		]
	],
	[
		"title" => "Тема 2: Frontend основы",
		"subs" => [
			[
				"title" => "Подтема 2.1: Семантический HTML",
				"content" => "Используйте теги header, main, section, article, footer для структуры. Атрибут lang помогает доступности. Текстовая иерархия через h1–h6."
			],
			[
				"title" => "Подтема 2.2: Базовый CSS",
				"content" => "Современная вёрстка строится на Flex и Grid. Принципы: каскад, специфичность, box-model. Следите за контрастом и читаемостью."
			],
			[
				"title" => "Подтема 2.3: JS и DOM",
				"content" => "Манипуляции DOM через document.querySelector, addEventListener. Событийная модель и делегирование упрощают код. Не блокируйте основной поток долгими операциями."
			],
		]
	],
];
?>
<!doctype html>
<html lang="ru">

<head>
	<meta charset="utf-8" />
	<title>Knowledge Base Test</title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<style>
		:root {
			font-family: system-ui, Arial, sans-serif;
		}

		body {
			margin: 0;
			background: #f6f7fb;
		}

		.wrap {
			max-width: 1000px;
			margin: 24px auto;
			padding: 16px;
			background: #fff;
			border-radius: 14px;
			box-shadow: 0 6px 24px rgba(0, 0, 0, .06);
		}

		h1 {
			margin: 0 0 16px 0;
			font-size: 22px
		}

		.grid {
			display: grid;
			grid-template-columns: 1fr 1fr 2fr;
			gap: 16px;
		}

		.col {
			border: 1px solid #e8e8ee;
			border-radius: 12px;
			overflow: hidden;
		}

		.col h2 {
			margin: 0;
			padding: 10px 12px;
			font-size: 16px;
			background: #fafbff;
			border-bottom: 1px solid #eee;
		}

		.list {
			padding: 8px;
			max-height: 320px;
			overflow: auto;
		}

		.item {
			padding: 8px 10px;
			margin: 6px 0;
			border: 1px solid #e7e7ef;
			border-radius: 10px;
			cursor: pointer;
			user-select: none;
		}

		.item:hover {
			background: #fff9cc;
		}

		.item.active {
			background: #ffef80;
			border-color: #e0c200;
		}

		.content {
			padding: 14px;
			line-height: 1.5;
		}

		.muted {
			color: #666;
			font-size: 14px;
		}
	</style>
</head>

<body>
	<div class="wrap">
		<h1>ТЗ:мини-база знаний</h1>
		<div class="grid">
			<section class="col">
				<h2>Тема</h2>
				<div id="topics" class="list"></div>
			</section>
			<section class="col">
				<h2>Подтема</h2>
				<div id="subtopics" class="list"></div>
			</section>
			<section class="col">
				<h2>Содержание</h2>
				<div id="content" class="content muted"></div>
			</section>
		</div>
	</div>

	<script>
		const data = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		let currentTopic = 0;
		let currentSub = 0;

		const topicsEl = document.getElementById('topics');
		const subsEl = document.getElementById('subtopics');
		const contentEl = document.getElementById('content');

		function renderTopics() {
			topicsEl.innerHTML = '';
			data.forEach((t, i) => {
				const div = document.createElement('div');
				div.className = 'item' + (i === currentTopic ? ' active' : '');
				div.textContent = t.title;
				div.onclick = () => {
					if (currentTopic !== i) {
						currentTopic = i;
						currentSub = 0;
						renderAll();
					}
				};
				topicsEl.appendChild(div);
			});
		}

		function renderSubs() {
			subsEl.innerHTML = '';
			data[currentTopic].subs.forEach((s, i) => {
				const div = document.createElement('div');
				div.className = 'item' + (i === currentSub ? ' active' : '');
				div.textContent = s.title;
				div.onclick = () => {
					currentSub = i;
					renderContent();
					renderSubs();
				};
				subsEl.appendChild(div);
			});
		}

		function renderContent() {
			const sub = data[currentTopic].subs[currentSub];
			contentEl.textContent = sub.content;
			contentEl.classList.remove('muted');
		}

		function renderAll() {
			renderTopics();
			renderSubs();
			renderContent();
		}
		renderAll();
	</script>
</body>

</html>