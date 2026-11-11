// =========================================================
// Illuminos — клиентский JavaScript
// Прежние возможности (валидация регистрации и входа без
// перезагрузки, избранное) сохранены; добавлены тосты, герой-
// карусель, ленты, подсказки поиска, оплата с подтверждением.
// =========================================================
(() => {
'use strict';

const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------- Уведомления (тосты) ----------
// ioToast('Текст', {type: 'success'|'error'|'gold'|'info', action: {label, href | onClick}, duration})
function ioToast(message, opts = {}) {
    let host = $('#io-toasts');
    if (!host) {
        host = document.createElement('div');
        host.id = 'io-toasts';
        host.className = 'io-toasts';
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
    }
    while (host.children.length >= 3) host.firstElementChild.remove();

    const el = document.createElement('div');
    el.className = 'io-toast io-toast--' + (opts.type || 'info');
    el.setAttribute('role', opts.type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'io-toast-icon';
    const text = document.createElement('span');
    text.className = 'io-toast-text';
    text.textContent = message;
    el.append(icon, text);

    let timer;
    const close = () => {
        clearTimeout(timer);
        el.classList.remove('is-in');
        setTimeout(() => el.remove(), 300);
    };
    if (opts.action) {
        const a = document.createElement(opts.action.href ? 'a' : 'button');
        a.className = 'io-toast-action';
        a.textContent = opts.action.label;
        if (opts.action.href) a.href = opts.action.href;
        else a.type = 'button';
        a.addEventListener('click', () => { if (opts.action.onClick) opts.action.onClick(); close(); });
        el.appendChild(a);
    }
    host.appendChild(el);
    requestAnimationFrame(() => el.classList.add('is-in'));
    timer = setTimeout(close, opts.duration || 4200);
    el.addEventListener('mouseenter', () => clearTimeout(timer));
    el.addEventListener('mouseleave', () => { timer = setTimeout(close, 2000); });
    return close;
}
window.ioToast = ioToast;

// ---------- Шапка: тень при прокрутке ----------
const navbar = $('.io-navbar');
if (navbar) {
    const onScroll = () => navbar.classList.toggle('is-scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, {passive: true});
}

// ---------- Поиск/фильтр с шапки: прокрутить к результатам ----------
const catalog = $('#catalog');
if (catalog && !location.hash && /[?&](q|type|genre|sort)=/.test(location.search)) {
    requestAnimationFrame(() => catalog.scrollIntoView({block: 'start'}));
}

// ---------- Валидация регистрации БЕЗ перезагрузки страницы (Задание 5) ----------
// Правила и сообщения совпадают с серверными (backend/auth.php).
const RULES = {
    first_name: (v) => v.trim() ? '' : 'Укажите имя и фамилию.',
    last_name:  (v) => v.trim() ? '' : 'Укажите имя и фамилию.',
    login:      (v) => /^[a-zA-Z0-9_]{3,20}$/.test(v || '') ? '' : 'Логин: 3–20 символов (буквы, цифры, _).',
    email:      (v) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v || '') ? '' : 'Некорректный e-mail.',
    password:   (v) => (v || '').length >= 6 ? '' : 'Пароль не короче 6 символов.',
};
const REGISTER_ORDER = ['first_name', 'last_name', 'login', 'email', 'password', 'password2', 'agree'];

function fieldError(form, name, message) {
    const input = form.elements[name];
    if (!input) return;
    const host = input.closest('.io-pass, .form-check') || input;
    let box = host.parentElement.querySelector(':scope > .io-field-error[data-for="' + name + '"]');
    if (message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        input.setAttribute('aria-invalid', 'true');
        if (!box) {
            box = document.createElement('div');
            box.className = 'io-field-error';
            box.dataset.for = name;
            host.after(box);
        }
        box.textContent = message;
    } else {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        if (box) box.remove();
    }
}

function registerFieldCheck(form, name) {
    const v = form.elements[name]?.value ?? '';
    if (name === 'password2') return v === form.elements.password.value ? '' : 'Пароли не совпадают.';
    if (name === 'agree') return form.elements.agree.checked ? '' : 'Нужно согласие с правилами регистрации.';
    return RULES[name](v);
}

function setBusy(btn, busy, busyText) {
    if (!btn) return;
    if (busy) {
        btn.dataset.label = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<span class="io-spinner" aria-hidden="true"></span>' + busyText;
    } else {
        btn.disabled = false;
        btn.textContent = btn.dataset.label || btn.textContent;
    }
}

function showFormError(form, errorsBox, message, fieldName) {
    errorsBox.textContent = message;
    form.classList.remove('io-shake');
    void form.offsetWidth; // перезапуск анимации
    if (!reduceMotion) form.classList.add('io-shake');
    if (fieldName && form.elements[fieldName]) form.elements[fieldName].focus();
}

const registerForm = document.getElementById('register-form');
if (registerForm) {
    const errorsBox = document.getElementById('form-errors');
    const touched = new Set();

    // Проверка поля при уходе из него и при вводе (после первого ухода)
    REGISTER_ORDER.forEach((name) => {
        const el = registerForm.elements[name];
        if (!el) return;
        const check = () => {
            if (!touched.has(name)) return;
            fieldError(registerForm, name, registerFieldCheck(registerForm, name));
            if (name === 'password' && touched.has('password2')) {
                fieldError(registerForm, 'password2', registerFieldCheck(registerForm, 'password2'));
            }
        };
        el.addEventListener('blur', () => { touched.add(name); check(); });
        el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', check);
    });

    // Индикатор надёжности пароля
    const pass = registerForm.elements.password;
    const meter = $('[data-strength]', registerForm);
    if (pass && meter) {
        const label = $('span', meter);
        const names = ['', 'Слабый', 'Средний', 'Хороший', 'Надёжный'];
        pass.addEventListener('input', () => {
            const v = pass.value;
            let s = 0;
            if (v.length >= 6) s++;
            if (v.length >= 10) s++;
            if (/[a-zа-я]/.test(v) && /[A-ZА-Я]/.test(v)) s++;
            if (/\d/.test(v) && /[^\w\s]/.test(v)) s++;
            if (!v) s = 0;
            meter.dataset.level = String(s);
            label.textContent = names[s];
        });
    }

    // Проверка занятости логина (подсказка; окончательная проверка — на сервере)
    const loginInput = registerForm.elements.login;
    const hint = document.createElement('div');
    hint.className = 'io-field-hint';
    hint.setAttribute('aria-live', 'polite');
    loginInput.after(hint);
    let loginTimer, loginCtl;
    loginInput.addEventListener('input', () => {
        clearTimeout(loginTimer);
        hint.textContent = '';
        hint.className = 'io-field-hint';
        const v = loginInput.value;
        if (RULES.login(v)) return; // формат неверный — подсказку не показываем
        loginTimer = setTimeout(async () => {
            if (loginCtl) loginCtl.abort();
            loginCtl = new AbortController();
            try {
                const r = await fetch('/backend/auth.php?action=check_login&login=' + encodeURIComponent(v), {signal: loginCtl.signal});
                const j = await r.json();
                if (j.ok && loginInput.value === v) {
                    hint.textContent = j.available ? 'Логин свободен.' : 'Этот логин уже занят.';
                    hint.className = 'io-field-hint ' + (j.available ? 'io-field-hint--ok' : 'io-field-hint--bad');
                }
            } catch (e) { /* сеть недоступна или запрос отменён — подсказка необязательна */ }
        }, 450);
    });

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorsBox.textContent = '';

        // Клиентская валидация (зеркало серверной): первая ошибка по порядку полей
        for (const name of REGISTER_ORDER) {
            const msg = registerFieldCheck(registerForm, name);
            touched.add(name);
            fieldError(registerForm, name, msg);
            if (msg) return showFormError(registerForm, errorsBox, msg, name);
        }

        const btn = $('button[type="submit"]', registerForm);
        setBusy(btn, true, 'Создаём аккаунт…');
        try {
            const res = await fetch('/backend/auth.php?action=register', {
                method: 'POST',
                body: new FormData(registerForm),
            });
            const json = await res.json();
            if (json.ok) {
                btn.innerHTML = 'Готово';
                window.location = json.redirect || '/index.php';
                return;
            }
            showFormError(registerForm, errorsBox, json.error || 'Ошибка регистрации.');
        } catch (err) {
            showFormError(registerForm, errorsBox, 'Не удалось связаться с сервером. Проверьте соединение и повторите.');
        }
        setBusy(btn, false);
    });
}

// ---------- Авторизация без перезагрузки ----------
const loginForm = document.getElementById('login-form');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorsBox = document.getElementById('form-errors');
        errorsBox.textContent = '';
        const btn = $('button[type="submit"]', loginForm);
        setBusy(btn, true, 'Входим…');
        try {
            const res = await fetch('/backend/auth.php?action=login', {
                method: 'POST',
                body: new FormData(loginForm),
            });
            const json = await res.json();
            if (json.ok) {
                btn.innerHTML = 'Готово';
                window.location = json.redirect || '/index.php';
                return;
            }
            showFormError(loginForm, errorsBox, json.error || 'Ошибка входа.', 'password');
        } catch (err) {
            showFormError(loginForm, errorsBox, 'Не удалось связаться с сервером. Проверьте соединение и повторите.');
        }
        setBusy(btn, false);
    });
}

// Показать/скрыть пароль
document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-toggle-pass]');
    if (!t) return;
    const input = t.closest('.io-pass').querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    t.setAttribute('aria-pressed', String(show));
    t.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
});

// ---------- Кнопки «В избранное» (Задание 5: избранное) ----------
function paintFav(btn, inFav) {
    btn.classList.toggle('active', inFav);
    btn.setAttribute('aria-pressed', String(inFav));
    const icon = $('[data-fav-icon]', btn);
    const label = $('[data-fav-label]', btn);
    if (icon) {
        icon.textContent = inFav ? '♥' : '♡';
        if (label) label.textContent = inFav ? 'В избранном' : 'В избранное';
    } else {
        btn.textContent = inFav ? '♥' : '♡';
    }
    btn.title = inFav ? 'Убрать из избранного' : 'В избранное';
}

async function toggleFav(btn) {
    if (btn.dataset.busy) return;
    btn.dataset.busy = '1';
    try {
        const res = await fetch('/cabinet.php?action=toggle_fav', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'content_id=' + encodeURIComponent(btn.dataset.id),
        });
        if (res.status === 403) {
            ioToast('Сессия истекла. Войдите снова.', {type: 'error', action: {label: 'Войти', href: '/login.php'}});
            return;
        }
        const json = await res.json();
        if (!json.ok) throw new Error('fail');

        // Синхронизируем все кнопки этого фильма на странице (герой, ленты, каталог)
        $$('.io-fav-btn[data-id="' + btn.dataset.id + '"]').forEach((b) => paintFav(b, json.in_favorites));
        if (!reduceMotion) {
            btn.classList.remove('is-pop');
            void btn.offsetWidth;
            if (json.in_favorites) btn.classList.add('is-pop');
        }

        // В кабинете карточка не пропадает: её можно вернуть
        const col = btn.closest('[data-fav-grid] .io-fav-col');
        if (col) col.classList.toggle('is-removed', !json.in_favorites);

        if (json.in_favorites) {
            ioToast('Добавлено в избранное', {type: 'gold'});
        } else {
            ioToast('Убрано из избранного', {action: {label: 'Вернуть', onClick: () => toggleFav(btn)}});
        }
    } catch (err) {
        ioToast('Не удалось обновить избранное. Повторите попытку.', {type: 'error'});
    } finally {
        delete btn.dataset.busy;
    }
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.io-fav-btn');
    if (!btn) return;
    e.preventDefault();
    if (btn.dataset.guest) {
        ioToast('Войдите, чтобы добавлять в избранное', {action: {label: 'Войти', href: '/login.php'}});
        return;
    }
    toggleFav(btn);
});

// ---------- Герой-карусель на главной + фоновые трейлеры ----------
const hero = $('[data-hero]');
if (hero) {
    const slides = $$('.io-hero-slide', hero);
    const thumbs = $$('.io-hero-thumb, .io-hero-dot', hero);
    const toggle = $('.io-hero-toggle:not(.io-hero-sound)', hero);
    const soundBtn = $('[data-hero-sound]', hero);
    let cur = 0;
    let stopped = reduceMotion; // при «уменьшить движение» автосмена выключена

    // ---------- Фоновые трейлеры: локальные <video> ----------
    // Грузится лениво (только активный и соседние слайды), по умолчанию без звука;
    // звук — отдельной кнопкой. Видео зациклено и играет только на активном слайде.
    const videoEls = $$('[data-hero-video]', hero);
    let soundOn = false; // звук фонового трейлера (по умолчанию выключен)

    function syncVideos() {
        const active = slides[cur];
        videoEls.forEach((v) => {
            const slide = v.closest('.io-hero-slide');
            const isActive = slide === active;
            const idx = slides.indexOf(slide);
            const near = idx >= 0 && Math.abs(idx - cur) <= 1;
            // ленивая загрузка: присваиваем src только близким слайдам
            if (near && !v.src && v.dataset.src) {
                v.src = v.dataset.src;
                v.load();
            }
            if (!v.src) return;
            if (isActive && !stopped && !document.hidden) {
                v.play().catch(() => {}); // автоплей может быть отклонён — фон останется градиентом
            } else {
                v.pause();
            }
            v.muted = !(soundOn && isActive);
        });
    }

    // Кнопка звука: включает звук активного трейлера (остальные — muted)
    if (soundBtn) {
        soundBtn.addEventListener('click', () => {
            soundOn = !soundOn;
            soundBtn.setAttribute('aria-pressed', String(soundOn));
            soundBtn.setAttribute('aria-label', soundOn ? 'Выключить звук трейлера' : 'Включить звук трейлера');
            soundBtn.title = soundOn ? 'Выключить звук трейлера' : 'Включить звук трейлера';
            syncVideos();
        });
    }

    const render = () => hero.classList.toggle('is-stopped', stopped);
    const setPlaying = () => {
        hero.classList.toggle('is-playing', !stopped && !document.hidden);
        syncVideos();
        restartTimer();
    };

    function go(i) {
        i = (i + slides.length) % slides.length;
        if (i === cur) return;
        slides[cur].classList.remove('is-active');
        slides[cur].setAttribute('aria-hidden', 'true');
        thumbs[cur] && thumbs[cur].classList.remove('is-active');
        thumbs[cur] && thumbs[cur].setAttribute('aria-current', 'false');
        cur = i;
        slides[cur].classList.add('is-active');
        slides[cur].removeAttribute('aria-hidden');
        thumbs[cur] && thumbs[cur].classList.add('is-active');
        thumbs[cur] && thumbs[cur].setAttribute('aria-current', 'true');
        syncVideos();
        restartTimer();
    }

    thumbs.forEach((t, i) => t.addEventListener('click', () => {
        go(i);
        // перезапуск таймера автосмены
        restartTimer();
    }));

    // Автосмена по таймеру 7 секунд (полосы прогресса на миниатюрах убраны)
    const SLIDE_MS = 7000;
    let slideTimer = null;
    const restartTimer = () => {
        clearInterval(slideTimer);
        if (!stopped && !reduceMotion && slides.length > 1) {
            slideTimer = setInterval(() => { if (!document.hidden && !hero.classList.contains('is-paused')) go(cur + 1); }, SLIDE_MS);
        }
    };

    // Пауза при наведении и фокусе, кнопка пауза/пуск (WCAG 2.2.2)
    // Наведение не ставит трейлер на паузу: видео — живой фон; паузит только смена слайдов
    const pause = (on) => hero.classList.toggle('is-paused', on);
    hero.addEventListener('mouseenter', () => pause(true));
    hero.addEventListener('mouseleave', () => pause(false));
    // Пауза по фокусу — только при навигации с клавиатуры (после клика мышью автосмена продолжается)
    hero.addEventListener('focusin', (e) => { if (e.target.matches(':focus-visible')) pause(true); });
    hero.addEventListener('focusout', () => pause(false));
    document.addEventListener('visibilitychange', setPlaying);
    if (toggle) {
        toggle.setAttribute('aria-label', stopped ? 'Запустить автосмену' : 'Остановить автосмену');
        toggle.addEventListener('click', () => {
            stopped = !stopped;
            toggle.setAttribute('aria-label', stopped ? 'Запустить автосмену' : 'Остановить автосмену');
            render();
            setPlaying();
        });
    }
    // Клавиши ← → и свайп
    hero.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') go(cur + 1);
        if (e.key === 'ArrowLeft') go(cur - 1);
    });
    let x0 = null;
    hero.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, {passive: true});
    hero.addEventListener('touchend', (e) => {
        if (x0 === null) return;
        const dx = e.changedTouches[0].clientX - x0;
        x0 = null;
        if (Math.abs(dx) > 50) go(cur + (dx < 0 ? 1 : -1));
    }, {passive: true});

    render();
    setPlaying();
}

// ---------- Плеер-заглушка (страницы фильма/сериала) ----------
// По кнопке Play плеер «подключается к трансляции»: учебная заглушка,
// демонстрирующая поведение реального плеера без видеофайла.
const player = $('[data-player]');
if (player) {
    const playBtn = $('[data-player-play]', player);
    const bar = $('.io-player-bar-fill', player);
    playBtn.addEventListener('click', () => {
        const label = $('[data-player-play]', player);
        const icon = $('.io-player-play-icon', player);
        if (player.classList.contains('is-loading')) return;
        player.classList.add('is-loading');
        icon.classList.add('is-spinning');
        label.querySelector('.io-player-play-label').textContent = 'Подключаемся к трансляции…';
        ioToast('Плеер загружает трансляцию «' + player.dataset.title + '»', {type: 'info'});
        // Имитация буферизации: затем открываем доступ к потоку
        setTimeout(() => {
            player.classList.remove('is-loading');
            player.classList.add('is-onair');
            icon.classList.remove('is-spinning');
            label.querySelector('.io-player-play-label').textContent = 'Идёт трансляция';
            if (bar) bar.style.setProperty('--w', '0%');
            requestAnimationFrame(() => { if (bar) bar.style.setProperty('--w', '38%'); });
            ioToast('Трансляция доступна. В учебной версии плеера видео не подключено.', {type: 'success', duration: 6000});
        }, 1400);
    });
}

// ---------- Ленты карточек ----------
$$('[data-rail]').forEach((rail) => {
    const track = $('.io-rail-track', rail);
    const prev = $('[data-rail-prev]', rail);
    const next = $('[data-rail-next]', rail);
    let raf = 0;
    const update = () => {
        raf = 0;
        const max = track.scrollWidth - track.clientWidth;
        rail.classList.toggle('is-scrollable', max > 4);
        prev.disabled = track.scrollLeft <= 2;
        next.disabled = track.scrollLeft >= max - 2;
    };
    const schedule = () => { if (!raf) raf = requestAnimationFrame(update); };
    const step = () => Math.max(track.clientWidth * 0.9, 200);
    prev.addEventListener('click', () => track.scrollBy({left: -step(), behavior: reduceMotion ? 'auto' : 'smooth'}));
    next.addEventListener('click', () => track.scrollBy({left: step(), behavior: reduceMotion ? 'auto' : 'smooth'}));
    track.addEventListener('scroll', schedule, {passive: true});
    window.addEventListener('resize', schedule);
    update();
});

// ---------- Подсказки поиска ----------
const searchForm = $('form[role="search"]');
const searchInput = searchForm && $('input[name="q"]', searchForm);
if (searchInput) {
    const list = $('#ioSuggest');
    let items = [], active = -1, timer, ctl;

    const close = () => {
        list.hidden = true;
        list.replaceChildren();
        items = []; active = -1;
        searchInput.setAttribute('aria-expanded', 'false');
        searchInput.removeAttribute('aria-activedescendant');
    };
    const setActive = (i) => {
        items.forEach((a, k) => a.classList.toggle('is-active', k === i));
        active = i;
        if (i >= 0) searchInput.setAttribute('aria-activedescendant', items[i].id);
        else searchInput.removeAttribute('aria-activedescendant');
    };
    const highlight = (text, needle) => {
        const frag = document.createDocumentFragment();
        const at = text.toLowerCase().indexOf(needle.toLowerCase());
        if (at < 0 || !needle) { frag.append(text); return frag; }
        const mark = document.createElement('mark');
        mark.textContent = text.slice(at, at + needle.length);
        frag.append(text.slice(0, at), mark, text.slice(at + needle.length));
        return frag;
    };
    const render = (data, needle) => {
        list.replaceChildren();
        items = [];
        if (!data.length) {
            const li = document.createElement('li');
            li.className = 'io-suggest-empty';
            li.textContent = 'Ничего не найдено.';
            list.append(li);
        }
        data.forEach((d, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            const a = document.createElement('a');
            a.href = d.url;
            a.id = 'ioSuggest-' + i;
            const sw = document.createElement('span');
            sw.className = 'io-suggest-swatch';
            sw.style.setProperty('--h1', d.h1);
            sw.style.setProperty('--h2', d.h2);
            const box = document.createElement('span');
            const title = document.createElement('div');
            title.className = 'io-suggest-title';
            title.append(highlight(d.title, needle));
            const meta = document.createElement('div');
            meta.className = 'io-suggest-meta';
            meta.textContent = d.type_label + ' · ' + d.year + ' · ★ ' + d.rating;
            box.append(title, meta);
            a.append(sw, box);
            li.append(a);
            list.append(li);
            items.push(a);
        });
        if (data.length) {
            const li = document.createElement('li');
            li.className = 'io-suggest-all';
            const a = document.createElement('a');
            a.href = '/index.php?q=' + encodeURIComponent(needle);
            a.textContent = 'Все результаты по запросу «' + needle + '»';
            li.append(a);
            list.append(li);
        }
        list.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
        active = -1;
    };

    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        const v = searchInput.value.trim();
        if (v.length < 2) { close(); return; }
        timer = setTimeout(async () => {
            if (ctl) ctl.abort();
            ctl = new AbortController();
            try {
                const r = await fetch('/index.php?action=suggest&q=' + encodeURIComponent(v), {signal: ctl.signal});
                if (!r.ok) return;
                const data = await r.json();
                if (searchInput.value.trim() === v) render(data, v);
            } catch (e) { /* запрос отменён или сети нет: обычный поиск по кнопке работает */ }
        }, 200);
    });
    searchInput.addEventListener('keydown', (e) => {
        if (list.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((active + 1) % items.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((active - 1 + items.length) % items.length); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); items[active].click(); }
        else if (e.key === 'Escape') { close(); }
    });
    document.addEventListener('click', (e) => { if (!searchForm.contains(e.target)) close(); });
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim().length >= 2 && !items.length) searchInput.dispatchEvent(new Event('input'));
    });

    // «/» — быстрый переход к поиску
    document.addEventListener('keydown', (e) => {
        const tag = (e.target.tagName || '').toLowerCase();
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !['input', 'textarea', 'select'].includes(tag) && !e.target.isContentEditable) {
            e.preventDefault();
            const toggler = $('.navbar-toggler');
            if (toggler && getComputedStyle(toggler).display !== 'none' && !$('#ioNav').classList.contains('show') && window.bootstrap) {
                window.bootstrap.Collapse.getOrCreateInstance($('#ioNav')).show();
            }
            searchInput.focus();
        }
    });
}

// ---------- Страницы фильма и сериала: «Поделиться» ----------
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-share]');
    if (!btn) return;
    const url = location.href.split('#')[0];
    if (navigator.share) {
        try { await navigator.share({title: document.title, url}); return; } catch (err) { if (err.name === 'AbortError') return; }
    }
    let ok = false;
    try { await navigator.clipboard.writeText(url); ok = true; } catch (err) {
        const ta = document.createElement('textarea');
        ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { ok = document.execCommand('copy'); } catch (e2) { ok = false; }
        ta.remove();
    }
    ioToast(ok ? 'Ссылка скопирована' : 'Не удалось скопировать ссылку', {type: ok ? 'success' : 'error'});
});

// ---------- Сериал: развернуть/свернуть все сезоны ----------
const seasonsToggle = $('[data-seasons-toggle]');
if (seasonsToggle && window.bootstrap) {
    seasonsToggle.addEventListener('click', () => {
        const open = seasonsToggle.dataset.state !== 'open';
        $$('.io-season .collapse').forEach((el) => {
            const c = window.bootstrap.Collapse.getOrCreateInstance(el, {toggle: false});
            open ? c.show() : c.hide();
        });
        seasonsToggle.dataset.state = open ? 'open' : 'closed';
        seasonsToggle.textContent = open ? 'Свернуть все' : 'Развернуть все';
    });
}

// ---------- Кабинет: очистка истории (два шага вместо диалога) ----------
const clearBtn = $('[data-clear-history]');
if (clearBtn) {
    let armed = false, t;
    clearBtn.addEventListener('click', async () => {
        if (!armed) {
            armed = true;
            clearBtn.textContent = 'Нажмите ещё раз, чтобы очистить';
            clearBtn.classList.add('is-armed');
            t = setTimeout(() => { armed = false; clearBtn.textContent = 'Очистить'; clearBtn.classList.remove('is-armed'); }, 4000);
            return;
        }
        clearTimeout(t);
        try {
            const res = await fetch('/cabinet.php?action=clear_history', {method: 'POST'});
            const json = await res.json();
            if (!json.ok) throw new Error('fail');
            const box = $('[data-history]');
            box.innerHTML = '<p class="text-secondary mb-0">История пуста.</p>';
            clearBtn.remove();
            const count = $('[data-stat-history]');
            if (count) count.textContent = '0';
            ioToast('История просмотра очищена', {type: 'success'});
        } catch (err) {
            armed = false;
            clearBtn.textContent = 'Очистить';
            clearBtn.classList.remove('is-armed');
            ioToast('Не удалось очистить историю.', {type: 'error'});
        }
    });
}

// ---------- Оплата: подтверждение покупки ----------
const payModalEl = $('#payModal');
if (payModalEl && window.bootstrap) {
    const modal = new window.bootstrap.Modal(payModalEl);
    let pending = null;
    $$('.js-pay-form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            pending = form;
            $('[data-pay-name]', payModalEl).textContent = form.dataset.name;
            $('[data-pay-price]', payModalEl).textContent = form.dataset.price;
            modal.show();
        });
    });
    const confirm = $('[data-pay-confirm]', payModalEl);
    confirm.addEventListener('click', () => {
        if (!pending) return;
        setBusy(confirm, true, 'Обрабатываем платёж…');
        pending.submit();
    });
    payModalEl.addEventListener('hidden.bs.modal', () => { pending = null; setBusy(confirm, false); confirm.textContent = 'Подтвердить и оплатить'; });
}
})();
