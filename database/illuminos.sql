-- =========================================================
-- База данных Illuminos (онлайн-кинотеатр)
-- Импортировать в PhpMyAdmin
-- =========================================================
CREATE DATABASE IF NOT EXISTS illuminos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE illuminos;

-- Пользователи (роли: admin, subscriber, user)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    patronymic VARCHAR(50) DEFAULT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','subscriber','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Категории контента (жанры)
CREATE TABLE genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- Фильмы и сериалы (единая таблица контента)
CREATE TABLE content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('film','serial') NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    year SMALLINT,
    rating DECIMAL(3,1) DEFAULT 0,
    director VARCHAR(150),
    actors TEXT,
    trailer_url VARCHAR(255) DEFAULT NULL,
    is_new TINYINT(1) DEFAULT 0,
    is_popular TINYINT(1) DEFAULT 0,
    genre_id INT,
    FOREIGN KEY (genre_id) REFERENCES genres(id)
);

-- Сезоны сериалов
CREATE TABLE seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    number INT NOT NULL,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);

-- Эпизоды сезонов
CREATE TABLE episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    number INT NOT NULL,
    title VARCHAR(150),
    duration_min INT DEFAULT 45,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

-- Избранное (сохранение фильмов/сериалов)
CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fav (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);

-- История просмотра
CREATE TABLE watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);

-- Тарифы подписки
CREATE TABLE tariffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    description VARCHAR(255)
);

-- Платежи
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tariff_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('new','paid','cancelled') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tariff_id) REFERENCES tariffs(id)
);

-- Активная подписка пользователя
CREATE TABLE user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tariff_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tariff_id) REFERENCES tariffs(id)
);

-- ---------- Демо-данные ----------
INSERT INTO genres (name) VALUES
('Драма'), ('Комедия'), ('Ужасы'), ('Фантастика'), ('Боевик'), ('Мелодрама');

-- Тарифы подписки (описание — список возможностей через запятую)
INSERT INTO tariffs (name, price, duration_days, description) VALUES
('Старт', 199.00, 30, 'SD 480p на смартфоне, 1 экран, 10 часов в месяц, реклама перед сеансом'),
('Базовый', 299.00, 30, 'HD 720p, 1 устройство, без лимита времени, без рекламы, избранное и история'),
('Стандарт', 499.00, 30, 'Full HD 1080p, 2 устройства одновременно, без рекламы, скачивание на 1 устройство, персональные рекомендации'),
('Премиум', 899.00, 30, '4K HDR + Dolby Atmos, 4 устройства одновременно, скачивание на 4 устройства, ранний доступ к новинкам, пространственный звук');

INSERT INTO content (type, title, description, year, rating, director, actors, trailer_url, is_new, is_popular, genre_id) VALUES
('film','Небесный код','Программист обнаруживает в коде спутника сообщение от внеземной цивилизации.',2026,8.4,'А. Волков','И. Смирнова, П. Гранин, М. Ли','https://www.youtube.com/embed/dQw4w9WgXcQ',1,1,4),
('film','Тишина города','Детектив расследует исчезновение музыканта в ночном городе.',2025,7.8,'К. Орлова','Д. Меньшов, А. Радова','',1,1,1),
('film','Смех сквозь стены','Комедия о соседях, случайно запустивших подпольное радио.',2024,7.1,'О. Петров','С. Бурунов, Л. Гришаева','',0,1,2),
('film','Северный свет','Драма о встрече полярника и художницы в закрытом посёлке.',2026,8.0,'М. Хан','Е. Королёва, Т. Аксёнов','',1,0,1),
('film','Протокол «Ночь»','Киберпанк-боевик о группе хакеров, взломавших корпоративную сеть.',2025,7.5,'Р. Ким','А. Соколов, В. Тёрнер','',1,0,5),
('film','Дом у озера','Мистический хоррор о доме, который помнит всех жильцов.',2024,6.9,'Л. Мрачная','Н. Ефремова, Г. Даль','',0,0,3),
('serial','Хроники Лумы','Фантастический сериал о колонистах планеты с двумя солнцами.',2026,8.9,'Дж. Райт','Э. Кларк, Х. Ндойе, Ю. Тарасова','https://www.youtube.com/embed/dQw4w9WgXcQ',1,1,4),
('serial','Отдел 13','Процедурал о следователях, раскрывающих «невозможные» преступления.',2025,8.2,'В. Стэн','М. Коул, А. Берг','',1,1,5),
('serial','Кафе «Утро»','Ситком о жизни небольшого кафе и его постоянных посетителей.',2024,7.6,'Г. Солнцев','П. Мирова, К. Львов','',0,1,2),
('serial','Шёпот сна','Психологический триллер о сомнологе, читающей чужие сны.',2026,8.6,'И. Нойман','С. Вельц, А. Крамер','',1,0,3);

INSERT INTO seasons (content_id, number) VALUES
(7,1),(7,2),(8,1),(8,2),(9,1),(10,1);

INSERT INTO episodes (season_id, number, title, duration_min) VALUES
(1,1,'Прибытие',52),(1,2,'Двойной рассвет',49),(1,3,'Пыльные бури',55),
(2,1,'Новая орбита',58),(2,2,'Сигнал тишины',54),
(3,1,'Холодное дело',46),(3,2,'Отпечаток',44),(3,3,'Седьмая дверь',47),
(4,1,'Обратный отсчёт',49),(4,2,'Чёрный ящик',51),
(5,1,'Первый кофе',24),(5,2,'Сироп для всех',23),(5,3,'Закрытый вечер',26),
(6,1,'Фаза один',50),(6,2,'Город спит',48),(6,3,'Пробуждение',53);
