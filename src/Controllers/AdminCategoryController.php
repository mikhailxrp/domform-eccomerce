<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/Category.php';
require_once ROOT_PATH . '/src/Core/Slug.php';

class AdminCategoryController
{
    public function index(): void
    {
        requireRole(['manager', 'admin']);

        render('admin/categories/index', [
            'title'      => 'Категории',
            'categories' => getCategoriesFlat(),
        ]);
    }

    public function create(): void
    {
        requireRole(['manager', 'admin']);

        $this->renderForm(null, [], []);
    }

    public function store(): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $input  = $this->categoryInputFromRequest();
        $errors = $this->validateCategoryInput($input, null);

        if (in_array(true, $errors, true)) {
            $this->renderForm(null, $input, $errors);
            return;
        }

        $id = createCategory($input);
        if ($id === null) {
            $this->renderForm(null, $input, ['slug_taken' => true]);
            return;
        }

        setFlash('success', 'Категория создана.');
        redirect('/admin/categories');
    }

    public function edit(string $id): void
    {
        requireRole(['manager', 'admin']);

        $category = findCategoryById((int) $id);
        if ($category === null) {
            abort404();
        }

        $this->renderForm($category, $category, []);
    }

    public function update(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        $category = findCategoryById((int) $id);
        if ($category === null) {
            abort404();
        }

        $input  = $this->categoryInputFromRequest();
        $errors = $this->validateCategoryInput($input, (int) $id);

        if (in_array(true, $errors, true)) {
            $this->renderForm($category, $input, $errors);
            return;
        }

        if (!updateCategory((int) $id, $input)) {
            $this->renderForm($category, $input, ['slug_taken' => true]);
            return;
        }

        setFlash('success', 'Категория обновлена.');
        redirect('/admin/categories');
    }

    public function delete(string $id): void
    {
        requireRole(['manager', 'admin']);
        requireCsrf();

        if (!deleteCategory((int) $id)) {
            setFlash('error', 'Нельзя удалить категорию, в которой есть товары.');
            redirect('/admin/categories');
        }

        setFlash('success', 'Категория удалена.');
        redirect('/admin/categories');
    }

    private function categoryInputFromRequest(): array
    {
        $name       = trim((string) input('name', ''));
        $slugRaw    = trim((string) input('slug', ''));
        $parentId   = (int) input('parent_id', 0);
        $description = trim((string) input('description', ''));
        $sortOrder  = (int) input('sort_order', 0);

        return [
            'name'        => $name,
            'slug'        => slugify($slugRaw !== '' ? $slugRaw : $name),
            'parent_id'   => $parentId > 0 ? $parentId : null,
            'description' => $description !== '' ? $description : null,
            'sort_order'  => $sortOrder,
        ];
    }

    /**
     * `$editingId` — `null` при создании; при редактировании категория
     * не может стать сама себе родителем (иначе `isValidCategoryParent()`
     * этого не поймает — она проверяет только глубину, не самоссылку).
     * `errors['slug']` — структурная пустота (название тоже пустое, или
     * состояло только из символов вне транслитерации), отдельно от
     * `errors['slug_taken']` (конфликт UNIQUE в БД, ставится в
     * `store()`/`update()`) — разные причины, разный текст под полем.
     */
    private function validateCategoryInput(array $input, ?int $editingId): array
    {
        $selfParent = $editingId !== null && $input['parent_id'] === $editingId;

        return [
            'name'      => $input['name'] === '',
            'slug'      => $input['slug'] === '',
            'parent_id' => $selfParent || !isValidCategoryParent($input['parent_id']),
        ];
    }

    private function renderForm(?array $category, array $old, array $errors): void
    {
        $excludeId = $category['id'] ?? null;

        $rootCategories = array_filter(
            getCategoriesFlat(),
            static fn (array $row): bool => $row['parent_id'] === null && $row['id'] !== $excludeId
        );

        render('admin/categories/form', [
            'title'          => $category === null ? 'Новая категория' : 'Редактирование категории',
            'category'       => $category,
            'rootCategories' => $rootCategories,
            'old'            => $old,
            'errors'         => $errors,
        ]);
    }
}
