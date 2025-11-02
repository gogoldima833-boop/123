<?php

// Включить все ошибки для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Включить все ошибки для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== НАСТРОЙКА БЕЗОПАСНОСТИ ====================
$ALLOWED_IPS = [
    '123.456.789.123',  // ЗАМЕНИ НА СВОЙ РЕАЛЬНЫЙ IP
    '192.168.1.100',    // Пример локального IP
    '111.222.333.444'   // Дополнительный IP если нужно
];

// Функция получения реального IP пользователя
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Проверка доступа по IP
function checkIPAccess($allowedIPs) {
    $userIP = getUserIP();
    
    // Если IP не в списке разрешенных
    if (!in_array($userIP, $allowedIPs)) {
        http_response_code(403);
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Доступ запрещен</title>
            <style>
                body { 
                    font-family: Arial; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-align: center;
                    padding: 100px 20px;
                    margin: 0;
                }
                .error-container {
                    background: rgba(255,0,0,0.1);
                    padding: 40px;
                    border-radius: 15px;
                    border: 1px solid rgba(255,100,100,0.3);
                    backdrop-filter: blur(10px);
                    max-width: 500px;
                    margin: 0 auto;
                }
                h1 { color: #ff4444; margin-bottom: 20px; }
                .ip-address { 
                    background: rgba(0,0,0,0.3); 
                    padding: 10px; 
                    border-radius: 5px; 
                    margin: 15px 0;
                    font-family: monospace;
                }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>🚫 ДОСТУП ЗАПРЕЩЕН</h1>
                <p>Доступ к этой странице ограничен по IP-адресу</p>
                <div class='ip-address'>Ваш IP: <strong>$userIP</strong></div>
                <p>Для получения доступа обратитесь к администратору</p>
                <p><a href='index.html' style='color: #4a90e2;'>← Вернуться на главную</a></p>
            </div>
        </body>
        </html>
        ");
    }
    
    return $userIP;
}

// ПРОВЕРЯЕМ ДОСТУП ПЕРЕД ВЫПОЛНЕНИЕМ ЛЮБОГО КОДА
$currentUserIP = checkIPAccess($ALLOWED_IPS);







// Настройки
$newsFile = 'news-data.json';
$uploadDir = 'uploads/';

// Создаем папку для загрузок
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Разрешить CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Обработка API запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Добавление новости
    if (isset($_POST['action']) && $_POST['action'] === 'add_news') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
        
        // Валидация
        if (empty($title) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Заполните заголовок и текст новости']);
            exit;
        }
        
        // Обработка загрузки изображения
        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $image = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (in_array($image['type'], $allowedTypes)) {
                $imageName = 'news_' . time() . '_' . uniqid() . '.' . pathinfo($image['name'], PATHINFO_EXTENSION);
                $imagePath = $uploadDir . $imageName;
                
                if (move_uploaded_file($image['tmp_name'], $imagePath)) {
                    // Файл успешно загружен
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ошибка загрузки изображения']);
                    exit;
                }
            }
        }
        
        // Загружаем существующие новости
        $newsData = [];
        if (file_exists($newsFile)) {
            $existingData = file_get_contents($newsFile);
            if ($existingData !== false) {
                $newsData = json_decode($existingData, true) ?? [];
            }
        }
        
        // Добавляем новую новость
        $newNews = [
            'id' => time(),
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'title' => $title,
            'content' => $content,
            'image' => $imagePath,
            'tags' => $tags
        ];
        
        array_unshift($newsData, $newNews);
        
        // Сохраняем
        if (file_put_contents($newsFile, json_encode($newsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => 'Новость добавлена']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ошибка сохранения файла']);
        }
        exit;
    }
    
    // Удаление новости
    if (isset($_POST['action']) && $_POST['action'] === 'delete_news') {
        $idToDelete = $_POST['id'] ?? '';
        
        if (file_exists($newsFile)) {
            $existingData = file_get_contents($newsFile);
            $newsData = json_decode($existingData, true) ?? [];
            
            $newsData = array_filter($newsData, function($news) use ($idToDelete) {
                return ($news['id'] ?? '') != $idToDelete;
            });
            
            // Переиндексируем массив
            $newsData = array_values($newsData);
            
            if (file_put_contents($newsFile, json_encode($newsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                echo json_encode(['success' => true, 'message' => 'Новость удалена']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ошибка удаления']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Файл новостей не найден']);
        }
        exit;
    }
}

// Получение всех новостей (для AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_news') {
    header('Content-Type: application/json');
    
    if (file_exists($newsFile)) {
        $existingData = file_get_contents($newsFile);
        $newsData = json_decode($existingData, true) ?? [];
        echo json_encode(['success' => true, 'news' => $newsData]);
    } else {
        echo json_encode(['success' => true, 'news' => []]);
    }
    exit;
}

// Если не API запрос, показываем HTML админ-панели
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель новостей</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .admin-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .admin-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .admin-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }

        @media (max-width: 768px) {
            .admin-content {
                grid-template-columns: 1fr;
            }
        }

        .form-section, .news-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #2c3e50;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            height: 120px;
            resize: vertical;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .btn-danger:hover {
            box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
        }

        .news-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }

        .news-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .news-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .news-date {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .news-image-preview {
            max-width: 100%;
            max-height: 150px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .news-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .news-actions .btn {
            width: auto;
            padding: 8px 15px;
            font-size: 14px;
        }

        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            display: none;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin: 10px 0;
            display: none;
        }

        .upload-area {
            border: 2px dashed #bdc3c7;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
            margin-bottom: 15px;
        }

        .upload-area:hover {
            border-color: #667eea;
        }

        .tag {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8em;
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>📝 Админ-панель новостей</h1>
            <p>Управление новостями компании</p>
        </div>

        <div class="admin-content">
            <!-- Левая колонка - Форма добавления -->
            <div class="form-section">
                <h3>➕ Добавить новость</h3>
                
                <div id="status" class="status"></div>

                <form id="newsForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="newsTitle">Заголовок новости:</label>
                        <input type="text" id="newsTitle" name="title" required placeholder="Введите заголовок...">
                    </div>

                    <div class="form-group">
                        <label for="newsDate">Дата публикации:</label>
                        <input type="date" id="newsDate" name="date" required>
                    </div>

                    <div class="form-group">
                        <label for="newsContent">Текст новости:</label>
                        <textarea id="newsContent" name="content" required placeholder="Введите текст новости..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Изображение:</label>
                        <div class="upload-area" onclick="document.getElementById('newsImage').click()">
                            <p>📁 Нажмите для загрузки изображения</p>
                            <p><small>или перетащите файл сюда</small></p>
                            <input type="file" id="newsImage" name="image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            <img id="imagePreview" class="image-preview" alt="Предпросмотр">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="newsTags">Теги (через запятую):</label>
                        <input type="text" id="newsTags" name="tags" placeholder="Новости, События, Компания...">
                    </div>

                    <button type="button" class="btn" onclick="addNews()">🚀 Опубликовать новость</button>
                </form>
            </div>

            <!-- Правая колонка - Список новостей -->
            <div class="news-section">
                <h3>📰 Все новости</h3>
                <div id="newsList">
                    <p>Загрузка новостей...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ВАЖНО: Используем текущий URL файла как API endpoint
        const API_URL = window.location.href;

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            // Устанавливаем сегодняшнюю дату по умолчанию
            document.getElementById('newsDate').value = new Date().toISOString().split('T')[0];
            
            // Загружаем существующие новости
            loadNews();
            
            // Настраиваем drag & drop
            setupDragAndDrop();
        });

        // Настройка drag & drop
        function setupDragAndDrop() {
            const uploadArea = document.querySelector('.upload-area');
            
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#667eea';
                uploadArea.style.background = '#f8f9fa';
            });
            
            uploadArea.addEventListener('dragleave', function() {
                uploadArea.style.borderColor = '#bdc3c7';
                uploadArea.style.background = '';
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#bdc3c7';
                uploadArea.style.background = '';
                
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type.startsWith('image/')) {
                    document.getElementById('newsImage').files = files;
                    previewImage(document.getElementById('newsImage'));
                }
            });
        }

        // Предпросмотр изображения
        function previewImage(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }

        // Загрузка всех новостей
        async function loadNews() {
            try {
                const response = await fetch(API_URL + '?action=get_news');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                
                if (data.success) {
                    displayNews(data.news);
                } else {
                    showStatus('Ошибка загрузки новостей', 'error');
                }
            } catch (error) {
                console.error('Ошибка загрузки новостей:', error);
                showStatus('Ошибка загрузки новостей: ' + error.message, 'error');
            }
        }

        // Отображение списка новостей
        function displayNews(news) {
            const container = document.getElementById('newsList');
            
            if (!news || news.length === 0) {
                container.innerHTML = '<p>Новостей пока нет</p>';
                return;
            }

            container.innerHTML = news.map(item => `
                <div class="news-item">
                    <h4>${escapeHtml(item.title)}</h4>
                    <div class="news-date">📅 ${formatDate(item.date)}</div>
                    <div class="news-content-preview">${escapeHtml(item.content.substring(0, 100))}...</div>
                    ${item.image ? `<img src="${item.image}" class="news-image-preview" alt="${escapeHtml(item.title)}">` : ''}
                    ${item.tags && item.tags.length > 0 ? `
                    <div style="margin-top: 10px;">
                        ${item.tags.map(tag => `<span class="tag">${escapeHtml(tag)}</span>`).join('')}
                    </div>
                    ` : ''}
                    <div class="news-actions">
                        <button class="btn btn-danger" onclick="deleteNews(${item.id})">🗑️ Удалить</button>
                    </div>
                </div>
            `).join('');
        }

        // Добавление новости
        async function addNews() {
            const form = document.getElementById('newsForm');
            const formData = new FormData(form);
            formData.append('action', 'add_news');

            // Валидация
            const title = document.getElementById('newsTitle').value.trim();
            const content = document.getElementById('newsContent').value.trim();
            
            if (!title || !content) {
                showStatus('Заполните заголовок и текст новости', 'error');
                return;
            }

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success) {
                    showStatus('✅ Новость успешно добавлена!', 'success');
                    form.reset();
                    document.getElementById('imagePreview').style.display = 'none';
                    document.getElementById('newsDate').value = new Date().toISOString().split('T')[0];
                    loadNews(); // Обновляем список
                } else {
                    showStatus('❌ Ошибка: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Ошибка добавления новости:', error);
                showStatus('❌ Ошибка сети: ' + error.message, 'error');
            }
        }

        // Удаление новости
        async function deleteNews(id) {
            if (!confirm('Вы уверены, что хотите удалить эту новость?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_news');
            formData.append('id', id);

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success) {
                    showStatus('✅ Новость удалена!', 'success');
                    loadNews(); // Обновляем список
                } else {
                    showStatus('❌ Ошибка при удалении: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Ошибка удаления новости:', error);
                showStatus('❌ Ошибка сети: ' + error.message, 'error');
            }
        }

        // Показать статус
        function showStatus(message, type) {
            const status = document.getElementById('status');
            status.textContent = message;
            status.className = 'status ' + type;
            
            setTimeout(() => {
                status.style.display = 'none';
            }, 5000);
        }

        // Форматирование даты
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        }

        // Экранирование HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>