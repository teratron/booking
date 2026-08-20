# TODO List

Список ниже является идеями для спецификаций, когда пункт будет внесён в спецификацию (обработан), отметить его в этом списке как завершённый `[x]` и больше не использовать, но оставить в истории для памяти и смотреть только не отмеченные пункты `[ ]`.

## Tasks

- [ ] ARIA (Accessible Rich Internet Applications), skills: <https://github.com/lifan0127/ai-research-assistant>, <https://github.com/pnoch/aria-skills>, <https://github.com/dylantarre/design-system-skills/tree/main/skills/accessibility/aria-patterns>
- [ ] WCAG (Web Content Accessibility Guidelines), skills: `npx skills add https://github.com/wshobson/agents --skill wcag-audit-patterns`, `npx skills add https://github.com/affaan-m/ecc --skill accessibility`, `npx skills add https://github.com/mastepanoski/claude-skills --skill wcag-accessibility-audit`
- [ ] прекрутить и настроить CI/CD (по максимуму, что необходимо)
- [ ] на стадии запуска продакшена прекрутить git flow систему и автоматезировать с помощью ИИ, добавить документацию как пользоваться и настроить git flow
- [ ] документация + промпт по развёртыванию проекта на английском и русском языках в разных markdown-файлах
- [ ] всплывающее уведомление подтверждения кукисов
- [ ] настройки для продакшена: .env.production, vite.config.js и т.д.
- [x] ~~Технологический стек (последние и стабильные версии): React или NextJS, TypeScript, PostgreSQL, PNPM, Vite, <https://github.com/facebook/astryx> на рассмотрение или Tailwind CSS + shadcn/ui, Fallow, Biome. По мере вёрстки и разбора механик и функционала, по проекту в figme, будут появляться новые элементы технологического стека, предлагай их на рассмотрение (возможно некоторые могут меняться).~~ **ОТМЕНЁН 2026-08-05** — реализация на этом стеке сохранена в теге `v0.1.34`. Актуальный стек ниже.
- [x] Технологический стек (утверждён 2026-08-05): **Laravel 13 + Filament 5 + PostgreSQL 18/PostGIS + Redis 8**, монолит, self-hosted. Blade + Livewire 4 + Alpine + Tailwind 4. Обоснование и полный набор пакетов — `.design/main/specifications/l2-tech-stack.md`.
- [x] [Booking](Booking.fig), <https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking?node-id=0-1&m=dev&t=4KPkPlrU7emBgEPJ-1>
- [x] логин/регистрация, оплата, админка - подобрать готовые решения, которые можно интегрировать в проект, чтобы не тратить время на разработку с нуля.
- [x] элементы web-интерфейса должны быть переиспользумыми, маштабируемыми, с возможностью кастомизации и расширения, чтобы не тратить время на разработку с нуля.
