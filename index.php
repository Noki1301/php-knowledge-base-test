<?php
/**
 * ТЗ (часть 2) — мини-CRM «Сделки» и «Контакты»
 * Один файл (index.php): UI + JSON API + файловое хранилище data.json
 * PHP 7.4+ совместимо. Без фреймворков.
 */

// ------------------------- Конфигурация -------------------------
const DATA_FILE = __DIR__ . '/data.json';

// Разрешим CORS для локального теста fetch в том же файле
header('X-Content-Type-Options: nosniff');

// ---------------------- Утилиты и хранение ----------------------
function load_data(): array {
    if (!file_exists(DATA_FILE)) {
        // Начальные данные по примеру из ТЗ
        $seed = [
            'deals' => [
                ['id' => 1, 'title' => 'Хотят люстру',   'amount' => 4000, 'contacts' => [15, 25]],
                ['id' => 14,'title' => 'Хотят светильник','amount' => 0,    'contacts' => [25]],
                ['id' => 2, 'title' => 'Пока думают',    'amount' => 0,    'contacts' => [15]],
            ],
            'contacts' => [
                ['id' => 15,'first_name' => 'Иван',    'last_name' => 'Петров',    'deals' => [1,2]],
                ['id' => 25,'first_name' => 'Наталья', 'last_name' => 'Сидорова', 'deals' => [1,14]],
                ['id' => 5, 'first_name' => 'Василий', 'last_name' => 'Иванов',   'deals' => []],
            ],
            'next_ids' => ['deal' => 15, 'contact' => 26],
        ];
        save_data($seed);
        return $seed;
    }
    $raw = file_get_contents(DATA_FILE);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = ['deals'=>[],'contacts'=>[],'next_ids'=>['deal'=>1,'contact'=>1]];
    $data['deals'] = array_values($data['deals']);
    $data['contacts'] = array_values($data['contacts']);
    return $data;
}

function save_data(array $data): void {
    // Пишем атомарно
    $tmp = DATA_FILE . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    rename($tmp, DATA_FILE);
}

function next_id(array &$data, string $kind): int {
    $n = $data['next_ids'][$kind] ?? 1;
    $data['next_ids'][$kind] = $n + 1;
    return $n;
}

function find_index_by_id(array $arr, int $id): int {
    foreach ($arr as $i => $row) if ((int)$row['id'] === $id) return $i;
    return -1;
}

function ensure_bidirectional_links(array &$data): void {
    // Синхронизируем связи deals <-> contacts
    foreach ($data['deals'] as &$d) {
        $d['contacts'] = array_values(array_unique(array_map('intval', $d['contacts'] ?? [])));
    }
    unset($d);

    foreach ($data['contacts'] as &$c) {
        $c['deals'] = array_values(array_unique(array_map('intval', $c['deals'] ?? [])));
    }
    unset($c);

    // Добавим недостающие обратные связи
    foreach ($data['deals'] as $d) {
        foreach ($d['contacts'] as $cid) {
            $ci = find_index_by_id($data['contacts'], (int)$cid);
            if ($ci >= 0 && !in_array($d['id'], $data['contacts'][$ci]['deals'], true)) {
                $data['contacts'][$ci]['deals'][] = $d['id'];
            }
        }
    }
    foreach ($data['contacts'] as $c) {
        foreach ($c['deals'] as $did) {
            $di = find_index_by_id($data['deals'], (int)$did);
            if ($di >= 0 && !in_array($c['id'], $data['deals'][$di]['contacts'], true)) {
                $data['deals'][$di]['contacts'][] = $c['id'];
            }
        }
    }
}

// --------------------------- JSON API ---------------------------
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $data = load_data();
    $action = $_GET['action'] ?? '';

    $result = ['ok' => true];

    try {
        if ($action === 'list') {
            $type = $_GET['type'] ?? '';
            if ($type === 'deals') $result['items'] = $data['deals'];
            elseif ($type === 'contacts') $result['items'] = $data['contacts'];
            else throw new Exception('Unknown type');
        }
        elseif ($action === 'get') {
            $type = $_GET['type'] ?? '';
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) throw new Exception('Bad id');
            if ($type === 'deals') {
                $i = find_index_by_id($data['deals'], $id);
                if ($i<0) throw new Exception('Not found');
                $result['item'] = $data['deals'][$i];
            } elseif ($type === 'contacts') {
                $i = find_index_by_id($data['contacts'], $id);
                if ($i<0) throw new Exception('Not found');
                $result['item'] = $data['contacts'][$i];
            } else throw new Exception('Unknown type');
        }
        elseif ($action === 'create' || $action === 'update') {
            $type = $_GET['type'] ?? '';
            $payload = json_decode(file_get_contents('php://input'), true) ?: [];

            if ($type === 'deals') {
                $title = trim($payload['title'] ?? '');
                if ($title === '') throw new Exception('Поле «Наименование» обязательно');
                $amount = (int)($payload['amount'] ?? 0);
                $contacts = array_values(array_unique(array_map('intval', $payload['contacts'] ?? [])));

                if ($action === 'create') {
                    $id = next_id($data, 'deal');
                    $data['deals'][] = ['id'=>$id,'title'=>$title,'amount'=>$amount,'contacts'=>$contacts];
                } else { // update
                    $id = (int)($payload['id'] ?? 0);
                    $i = find_index_by_id($data['deals'], $id);
                    if ($i<0) throw new Exception('Not found');
                    $data['deals'][$i]['title'] = $title;
                    $data['deals'][$i]['amount'] = $amount;
                    $data['deals'][$i]['contacts'] = $contacts;
                }
            }
            elseif ($type === 'contacts') {
                $first = trim($payload['first_name'] ?? '');
                if ($first === '') throw new Exception('Поле «Имя» обязательно');
                $last  = trim($payload['last_name'] ?? '');
                $deals = array_values(array_unique(array_map('intval', $payload['deals'] ?? [])));

                if ($action === 'create') {
                    $id = next_id($data, 'contact');
                    $data['contacts'][] = ['id'=>$id,'first_name'=>$first,'last_name'=>$last,'deals'=>$deals];
                } else { // update
                    $id = (int)($payload['id'] ?? 0);
                    $i = find_index_by_id($data['contacts'], $id);
                    if ($i<0) throw new Exception('Not found');
                    $data['contacts'][$i]['first_name'] = $first;
                    $data['contacts'][$i]['last_name']  = $last;
                    $data['contacts'][$i]['deals']      = $deals;
                }
            }
            else throw new Exception('Unknown type');

            ensure_bidirectional_links($data);
            save_data($data);
            $result['id'] = $id;
        }
        elseif ($action === 'delete') {
            $type = $_GET['type'] ?? '';
            $id = (int)($_GET['id'] ?? 0);
            if ($id<=0) throw new Exception('Bad id');

            if ($type === 'deals') {
                $i = find_index_by_id($data['deals'], $id);
                if ($i<0) throw new Exception('Not found');
                // удаляем ссылки у контактов
                foreach ($data['contacts'] as &$c) {
                    $c['deals'] = array_values(array_filter($c['deals'], fn($d) => (int)$d !== $id));
                }
                unset($c);
                array_splice($data['deals'], $i, 1);
            } elseif ($type === 'contacts') {
                $i = find_index_by_id($data['contacts'], $id);
                if ($i<0) throw new Exception('Not found');
                foreach ($data['deals'] as &$d) {
                    $d['contacts'] = array_values(array_filter($d['contacts'], fn($cid) => (int)$cid !== $id));
                }
                unset($d);
                array_splice($data['contacts'], $i, 1);
            } else throw new Exception('Unknown type');

            save_data($data);
        }
        else {
            throw new Exception('Unknown action');
        }

    } catch (Throwable $e) {
        http_response_code(400);
        $result = ['ok'=>false, 'error'=>$e->getMessage()];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------ UI ------------------------------
$data = load_data();
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ТЗ (часть 2): Сделки и Контакты</title>
  <style>
    :root { font-family: system-ui, Arial, sans-serif; }
    body { margin:0; background:#f6f7fb; }
    .wrap { max-width: 1100px; margin: 24px auto; padding: 16px; background:#fff; border-radius: 14px; box-shadow: 0 6px 24px rgba(0,0,0,.06);} 
    h1{margin:0 0 16px 0; font-size:22px}
    .grid { display:grid; grid-template-columns: 1fr 1.2fr 2fr; gap:16px; }
    .col { border:1px solid #e8e8ee; border-radius:12px; overflow:hidden; background:#fff; }
    .col h2{margin:0; padding:10px 12px; font-size:16px; background:#fafbff; border-bottom:1px solid #eee;}
    .list{padding:8px; max-height:420px; overflow:auto;}
    .item{padding:8px 10px; margin:6px 0; border:1px solid #e7e7ef; border-radius:10px; cursor:pointer; user-select:none;}
    .item:hover{background:#fff9cc}
    .item.active{background:#ffef80; border-color:#e0c200}
    .content{padding:12px}
    .row{display:flex; gap:10px; margin:8px 0}
    .label{min-width:160px; color:#555}
    .muted{color:#666; font-size:14px}
    .toolbar{display:flex; gap:8px; padding:8px; border-top:1px dashed #eee;}
    button{padding:8px 10px; border:1px solid #ddd; background:#f8f8ff; border-radius:8px; cursor:pointer}
    button.primary{background:#1c7ed6; color:#fff; border-color:#1c7ed6}
    button.danger{background:#e03131; color:#fff; border-color:#e03131}
    input[type="text"], input[type="number"], select{padding:8px; border:1px solid #ddd; border-radius:8px; width:100%}
    .two{display:grid; grid-template-columns:1fr 1fr; gap:8px}
    .pill{display:inline-block; padding:3px 8px; background:#eef3ff; border:1px solid #cdd7ff; border-radius:999px; margin:2px; font-size:12px}
    .hr{height:1px; background:#eee; margin:8px 0}
    .mini{font-size:12px; color:#777}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Сделки и Контакты — мини-CRM</h1>
    <div class="grid">
      <!-- Меню -->
      <section class="col">
        <h2>Меню</h2>
        <div class="list" id="menu">
          <div class="item active" data-type="deals">Сделки</div>
          <div class="item" data-type="contacts">Контакты</div>
        </div>
      </section>

      <!-- Список -->
      <section class="col">
        <h2>Список</h2>
        <div class="list" id="list"></div>
        <div class="toolbar">
          <button class="primary" id="btnNew">Добавить</button>
        </div>
      </section>

      <!-- Содержимое -->
      <section class="col">
        <h2>Содержимое</h2>
        <div class="content" id="content">
          <div class="muted">Выберите элемент слева, или создайте новый.</div>
        </div>
      </section>
    </div>
  </div>

<script>
const API = {
  async list(type){
    const r = await fetch(`?api=1&action=list&type=${type}`);
    return r.json();
  },
  async get(type,id){
    const r = await fetch(`?api=1&action=get&type=${type}&id=${id}`);
    return r.json();
  },
  async create(type,payload){
    const r = await fetch(`?api=1&action=create&type=${type}`, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
    return r.json();
  },
  async update(type,payload){
    const r = await fetch(`?api=1&action=update&type=${type}`, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
    return r.json();
  },
  async del(type,id){
    const r = await fetch(`?api=1&action=delete&type=${type}&id=${id}`);
    return r.json();
  }
};

let currentType = 'deals'; // deals | contacts
let currentId = null;

const menuEl = document.getElementById('menu');
const listEl = document.getElementById('list');
const contentEl = document.getElementById('content');
const btnNew = document.getElementById('btnNew');

menuEl.addEventListener('click', (e)=>{
  const it = e.target.closest('.item');
  if(!it) return;
  menuEl.querySelectorAll('.item').forEach(x=>x.classList.remove('active'));
  it.classList.add('active');
  currentType = it.dataset.type;
  currentId = null;
  renderList();
  renderContent();
});

btnNew.addEventListener('click', ()=>{
  renderEditor();
});

async function renderList(){
  const {ok, items, error} = await API.list(currentType);
  if(!ok){ listEl.innerHTML = `<div class='muted'>${error}</div>`; return; }
  listEl.innerHTML = '';
  items.forEach(item=>{
    const div = document.createElement('div');
    div.className = 'item' + (item.id===currentId ? ' active':'' );
    if(currentType==='deals'){
      div.innerHTML = `<strong>${escapeHtml(item.title)}</strong><div class='mini'>id: ${item.id}${item.amount?`, сумма: ${fmtAmount(item.amount)}`:''}</div>`;
    } else {
      div.innerHTML = `<strong>${escapeHtml(item.first_name)} ${escapeHtml(item.last_name||'')}</strong><div class='mini'>id: ${item.id}</div>`;
    }
    div.onclick = ()=>{ currentId = item.id; renderList(); renderContent(); };
    listEl.appendChild(div);
  });
}

async function renderContent(){
  if(!currentId){ contentEl.innerHTML = `<div class='muted'>Выберите элемент слева, или создайте новый.</div>`; return; }
  const {ok, item, error} = await API.get(currentType, currentId);
  if(!ok){ contentEl.innerHTML = `<div class='muted'>${error}</div>`; return; }

  if(currentType==='deals'){
    const left = (item.contacts||[]).map(id=>`<div>id контакта: <strong>${id}</strong></div>`).join('') || '<div class="muted">нет</div>';
    const right = (item.contacts||[]).map(id=>`<div class='pill'>${contactNameCached(id)}</div>`).join('');
    contentEl.innerHTML = `
      <div class='row'><div class='label'>id сделки</div><div>${item.id}</div></div>
      <div class='row'><div class='label'>Наименование</div><div>${escapeHtml(item.title)}</div></div>
      <div class='row'><div class='label'>Сумма</div><div>${fmtAmount(item.amount||0)}</div></div>
      <div class='hr'></div>
      <div class='two'>
        <div><div class='label'>Связанные контакты</div>${left}</div>
        <div><div class='label'>Имена</div>${right||'<div class=muted>—</div>'}</div>
      </div>
      <div class='toolbar'>
        <button class='primary' onclick='renderEditor()'>Редактировать</button>
        <button class='danger' onclick='doDelete()'>Удалить</button>
      </div>
    `;
  } else {
    const left = (item.deals||[]).map(id=>`<div>id сделки: <strong>${id}</strong></div>`).join('') || '<div class="muted">нет</div>';
    const right = (item.deals||[]).map(id=>`<div class='pill'>${dealTitleCached(id)}</div>`).join('');
    contentEl.innerHTML = `
      <div class='row'><div class='label'>id контакта</div><div>${item.id}</div></div>
      <div class='row'><div class='label'>Имя</div><div>${escapeHtml(item.first_name)}</div></div>
      <div class='row'><div class='label'>Фамилия</div><div>${escapeHtml(item.last_name||'')}</div></div>
      <div class='hr'></div>
      <div class='two'>
        <div><div class='label'>Связанные сделки</div>${left}</div>
        <div><div class='label'>Наименования</div>${right||'<div class=muted>—</div>'}</div>
      </div>
      <div class='toolbar'>
        <button class='primary' onclick='renderEditor()'>Редактировать</button>
        <button class='danger' onclick='doDelete()'>Удалить</button>
      </div>
    `;
  }
}

async function renderEditor(){
  // Данные для формы (если редактируем)
  let existing = null;
  if (currentId){
    const res = await API.get(currentType, currentId);
    if(res.ok) existing = res.item;
  }

  if(currentType==='deals'){
    const allContacts = (await API.list('contacts')).items || [];
    const selected = new Set((existing?.contacts)||[]);
    contentEl.innerHTML = `
      <div class='row'><div class='label'>Наименование *</div><div><input id='f_title' type='text' value='${escapeAttr(existing?.title||'')}' placeholder='Например: Новая сделка'></div></div>
      <div class='row'><div class='label'>Сумма</div><div><input id='f_amount' type='number' min='0' step='1' value='${existing?.amount??0}'></div></div>
      <div class='row'><div class='label'>Контакты</div>
        <div class='list' style='max-height:180px'>
          ${allContacts.map(c=>`<label style='display:block; margin:4px 0'>
            <input type='checkbox' value='${c.id}' ${selected.has(c.id)?'checked':''}> ${escapeHtml(c.first_name)} ${escapeHtml(c.last_name||'')} (id: ${c.id})
          </label>`).join('')||'<div class=muted>Список пуст</div>'}
        </div>
      </div>
      <div class='toolbar'>
        <button class='primary' onclick='saveDeal(${existing?existing.id:'null'})'>Сохранить</button>
        <button onclick='renderContent()'>Отмена</button>
      </div>
    `;
  } else {
    const allDeals = (await API.list('deals')).items || [];
    const selected = new Set((existing?.deals)||[]);
    contentEl.innerHTML = `
      <div class='row'><div class='label'>Имя *</div><div><input id='f_first' type='text' value='${escapeAttr(existing?.first_name||'')}' placeholder='Например: Иван'></div></div>
      <div class='row'><div class='label'>Фамилия</div><div><input id='f_last' type='text' value='${escapeAttr(existing?.last_name||'')}' placeholder='Например: Петров'></div></div>
      <div class='row'><div class='label'>Сделки</div>
        <div class='list' style='max-height:180px'>
          ${allDeals.map(d=>`<label style='display:block; margin:4px 0'>
            <input type='checkbox' value='${d.id}' ${selected.has(d.id)?'checked':''}> ${escapeHtml(d.title)} (id: ${d.id})
          </label>`).join('')||'<div class=muted>Список пуст</div>'}
        </div>
      </div>
      <div class='toolbar'>
        <button class='primary' onclick='saveContact(${existing?existing.id:'null'})'>Сохранить</button>
        <button onclick='renderContent()'>Отмена</button>
      </div>
    `;
  }
}

async function saveDeal(id){
  const title = document.getElementById('f_title').value.trim();
  const amount = parseInt(document.getElementById('f_amount').value||'0',10)||0;
  const contacts = [...contentEl.querySelectorAll('input[type="checkbox"]:checked')].map(x=>parseInt(x.value,10));
  const payload = {id, title, amount, contacts};
  const res = id? await API.update('deals', payload) : await API.create('deals', payload);
  if(!res.ok){ alert(res.error); return; }
  currentId = res.id || id;
  await renderList();
  await renderContent();
}

async function saveContact(id){
  const first_name = document.getElementById('f_first').value.trim();
  const last_name  = document.getElementById('f_last').value.trim();
  const deals = [...contentEl.querySelectorAll('input[type="checkbox"]:checked')].map(x=>parseInt(x.value,10));
  const payload = {id, first_name, last_name, deals};
  const res = id? await API.update('contacts', payload) : await API.create('contacts', payload);
  if(!res.ok){ alert(res.error); return; }
  currentId = res.id || id;
  await renderList();
  await renderContent();
}

async function doDelete(){
  if(!currentId) return;
  if(!confirm('Удалить элемент безвозвратно?')) return;
  const res = await API.del(currentType, currentId);
  if(!res.ok){ alert(res.error); return; }
  currentId = null;
  await renderList();
  await renderContent();
}

// Кэш имён для табличек
let _cache = {deals:new Map(), contacts:new Map()};
(async ()=>{
  const dl = await API.list('deals');
  const cl = await API.list('contacts');
  if(dl.ok) dl.items.forEach(d=>_cache.deals.set(d.id,d.title));
  if(cl.ok) cl.items.forEach(c=>_cache.contacts.set(c.id, `${c.first_name} ${c.last_name||''}`.trim()));
})();
function dealTitleCached(id){ return escapeHtml(_cache.deals.get(id)||`#${id}`); }
function contactNameCached(id){ return escapeHtml(_cache.contacts.get(id)||`#${id}`); }

function fmtAmount(n){ return (n||0).toLocaleString('ru-RU'); }
function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m])); }
function escapeAttr(s){ return escapeHtml(s).replace(/"/g,'&quot;'); }

// init
renderList();
renderContent();
</script>
</body>
</html>
