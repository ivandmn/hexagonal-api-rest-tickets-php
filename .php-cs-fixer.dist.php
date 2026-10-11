<?php

/*
 * Annotated PHP-CS-Fixer configuration.
 *
 * Stack: PHP 8.5, Symfony 7.4, Doctrine ORM 3, PHPUnit 13, PHPStan 2.
 *
 * Every rule has an explanation and, where useful, a "Before / After" example
 * of how it transforms the code.
 *
 * "Risky" rules may change the behaviour of the code (not only its style).
 * That's why ->setRiskyAllowed(true) is required for them to run.
 *
 * Notes marked with [NOTE] point out things you may want to review.
 *
 * Useful commands:
 *   vendor/bin/php-cs-fixer fix --dry-run --diff    # preview changes without touching files
 *   vendor/bin/php-cs-fixer fix                     # apply the changes
 *   vendor/bin/php-cs-fixer describe array_syntax   # official docs for a rule
 *   vendor/bin/php-cs-fixer describe @Symfony       # rules included in a set
 */

$finder = (new PhpCsFixer\Finder())
    // Look for files starting from the folder this file lives in (project root).
    ->in(__DIR__)
    // Folders that are skipped: cache/logs, dependencies and public files.
    ->exclude(['var', 'vendor', 'public'])
    // Ignore version control folders (.git, .svn...).
    ->ignoreVCS(true)
    // Ignore files starting with a dot (like this .php-cs-fixer.dist.php itself).
    ->ignoreDotFiles(true)
    // Files only, not directories.
    ->files()
    // PHP files only.
    ->name('*.php')
    // Specific files that are never touched: Symfony generates or manages them.
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // Reads the PHP version from composer.json ("php": ">=8.5") and applies
        // the migration rules for that version (modern PHP syntax).
        //   Before: $name = $name ?? 'Anonymous';
        //   After:  $name ??= 'Anonymous';
        '@autoPHPMigration' => true,

        // Same as above, but with the risky migration rules.
        //   Before: $doubles = array_map(function ($x) { return $x * 2; }, $items);
        //   After:  $doubles = array_map(fn ($x) => $x * 2, $items);
        '@autoPHPMigration:risky' => true,

        // Reads your PHPUnit version from composer.json (^13) and modernizes tests.
        //   Before: $this->assertInternalType('array', $result);
        //   After:  $this->assertIsArray($result);
        '@autoPHPUnitMigration:risky' => true,

        // =====================================================================
        // MODERN SYNTAX
        // =====================================================================

        // Short array syntax.
        //   Before: $ids = array(1, 2, 3);
        //   After:  $ids = [1, 2, 3];
        'array_syntax' => ['syntax' => 'short'],

        // Short syntax for array destructuring.
        //   Before: list($id, $name) = $row;
        //   After:  [$id, $name] = $row;
        'list_syntax' => ['syntax' => 'short'],

        // [NEW] Always use "?Type" instead of "null|Type" for single nullable types.
        //   Before: public function find(string $id): null|Task
        //   After:  public function find(string $id): ?Task
        'nullable_type_declaration' => ['syntax' => 'question_mark'],

        // [NEW] Sort union and intersection types, with null always last.
        // Same criteria as phpdoc_types_order, so native types and phpdoc match.
        //   Before: public function parse(string|int|null $value)
        //   After:  public function parse(int|string|null $value)
        'ordered_types' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'alpha'],

        // [NEW] Remove empty parentheses from attributes without arguments.
        // You use attributes everywhere (routes, OpenAPI, Doctrine, validation).
        //   Before: #[ORM\Entity()]
        //   After:  #[ORM\Entity]
        'attribute_empty_parentheses' => ['use_parentheses' => false],

        // [NEW] Closures that don't use $this become static. They don't capture
        // the object, so they use less memory and can't leak it. Risky: breaks
        // closures that are later bound to an object with Closure::bind().
        //   Before: $ids = array_map(fn (Task $t) => $t->id(), $tasks);
        //   After:  $ids = array_map(static fn (Task $t) => $t->id(), $tasks);
        'static_lambda' => true,

        // [NEW] Replace mutable DateTime with DateTimeImmutable. Immutable dates
        // avoid bugs where modifying a date changes it everywhere it's shared,
        // which fits well with value objects and entities. Risky: code that
        // relies on modifying the date in place ($date->modify(...) without
        // reassigning) stops working.
        //   Before: $createdAt = new \DateTime();
        //   After:  $createdAt = new \DateTimeImmutable();
        // [NOTE] Doctrine maps DateTimeImmutable properties to the
        // datetime_immutable type automatically, so entities keep working.
        'date_time_immutable' => true,

        // [NEW] Return the expression directly instead of assigning it to a
        // variable that is only returned.
        //   Before: $task = $this->repository->find($id);
        //
        //           return $task;
        //   After:  return $this->repository->find($id);
        'return_assignment' => true,

        // =====================================================================
        // STRICT TYPES AND COMPARISONS
        // =====================================================================

        // Adds declare(strict_types=1) at the top of every file. PHP then stops
        // converting types automatically: passing "5" to an int parameter fails.
        //   Before: <?php
        //           namespace App;
        //   After:  <?php
        //
        //           declare(strict_types=1);
        //
        //           namespace App;
        'declare_strict_types' => true,

        // Disabled. If enabled, it would turn == into === and != into !==.
        //   With true:  if ($status == 'done')  ->  if ($status === 'done')
        // [NOTE] None of your presets enable it, so this line only documents
        // that you don't want it.
        'strict_comparison' => false,

        // Disabled. If enabled, it would add the "strict" argument to functions
        // like in_array() or array_search().
        //   With true:  in_array($id, $ids)  ->  in_array($id, $ids, true)
        // [NOTE] Same as above: no preset enables it.
        'strict_param' => false,

        // =====================================================================
        // IMPORTS (use) AND NAMESPACES
        // =====================================================================

        // Remove "use" statements that aren't used in the file.
        //   Before: use App\Domain\Task\Task;
        //           use App\Domain\User\User;   // never used
        //   After:  use App\Domain\Task\Task;
        'no_unused_imports' => true,

        // Sort "use" statements alphabetically.
        //   Before: use Symfony\Component\HttpFoundation\Response;
        //           use App\Domain\Task\Task;
        //   After:  use App\Domain\Task\Task;
        //           use Symfony\Component\HttpFoundation\Response;
        'ordered_imports' => ['sort_algorithm' => 'alpha'],

        // Replace fully qualified class names in the code with a "use" at the
        // top and the short name (import_symbols => true).
        //   Before: public function find(\App\Domain\Task\TaskId $id): ?\App\Domain\Task\Task
        //   After:  use App\Domain\Task\Task;
        //           use App\Domain\Task\TaskId;
        //           ...
        //           public function find(TaskId $id): ?Task
        'fully_qualified_strict_types' => ['import_symbols' => true],

        // Classes, functions and constants from the global namespace (PHP's own,
        // like DateTimeImmutable) are NOT imported with "use": they're written
        // with a leading "\". If a global class is already imported, the "use"
        // is removed and the "\" is added.
        //   Before: use DateTimeImmutable;
        //           $now = new DateTimeImmutable();
        //   After:  $now = new \DateTimeImmutable();
        'global_namespace_import' => ['import_classes' => false, 'import_functions' => false, 'import_constants' => false],

        // Prefix ALL native PHP functions ('@all') with "\" inside namespaced
        // files. PHP doesn't have to look for the function in your namespace
        // first, and OPcache can optimize some of them (count, strlen, is_array...).
        // "strict" removes the "\" from functions that aren't native.
        //   Before: return sprintf('Task %s', $id);
        //   After:  return \sprintf('Task %s', $id);
        'native_function_invocation' => ['include' => ['@all'], 'scope' => 'namespaced', 'strict' => true],

        // =====================================================================
        // CLASSES AND METHODS
        // =====================================================================

        // Disabled. @Symfony orders class elements (traits, constants,
        // properties, constructor, methods...). With false, you decide the order.
        'ordered_class_elements' => false,

        // Exactly one blank line between methods.
        //   Before: public function a() {}
        //           public function b() {}
        //   After:  public function a() {}
        //
        //           public function b() {}
        // [NOTE] By listing only "method", constants and properties are no longer
        // separated (@Symfony separates them too). To keep that, use:
        //   ['elements' => ['const' => 'one', 'method' => 'one', 'property' => 'one', 'trait_import' => 'none']]
        'class_attributes_separation' => ['elements' => ['method' => 'one']],

        // Inside a class, use "self" instead of the class's own name.
        //   Before: final class TaskId { public static function generate(): TaskId { return new TaskId(...); } }
        //   After:  final class TaskId { public static function generate(): self { return new self(...); } }
        'self_accessor' => true,

        // Remove "final" from methods where it adds nothing: in classes that are
        // already final (can't be extended) and on private methods.
        //   Before: final class Foo { final public function bar() {} }
        //   After:  final class Foo { public function bar() {} }
        'no_unneeded_final_method' => true,

        // Disabled. If enabled, it would make classes marked @internal final.
        // [NOTE] None of your presets enable it: the line is redundant.
        'final_internal_class' => false,

        // Add ": void" to functions that don't return anything.
        //   Before: public function handle(AssignTaskToUserCommand $command)
        //           {
        //               $this->repository->save($task);
        //           }
        //   After:  public function handle(AssignTaskToUserCommand $command): void
        'void_return' => true,

        // =====================================================================
        // FUNCTIONS AND EXPRESSIONS
        // =====================================================================

        // Disabled. If enabled, it would merge consecutive isset() calls.
        //   With true:  isset($a) && isset($b)  ->  isset($a, $b)
        'combine_consecutive_issets' => false,

        // Disabled. If enabled, it would merge consecutive unset() calls.
        //   With true:  unset($a); unset($b);  ->  unset($a, $b);
        'combine_consecutive_unsets' => false,

        // Replace "alias" functions with the main function.
        //   sizeof($items)       ->  count($items)
        //   join(',', $items)    ->  implode(',', $items)
        //   chop($text)          ->  rtrim($text)
        //   is_integer($value)   ->  is_int($value)
        'no_alias_functions' => true,

        // Replace the isset() ternary with the ?? operator.
        //   Before: $page = isset($query['page']) ? $query['page'] : 1;
        //   After:  $page = $query['page'] ?? 1;
        'ternary_to_null_coalescing' => true,

        // Replace "return null;" with "return;" in functions without a return
        // type. Functions with a nullable type (?Task) are left alone, because
        // "return;" isn't valid there.
        //   Before: function find($id) { return null; }
        //   After:  function find($id) { return; }
        'simplified_null_return' => true,

        // Replace is_null() with a strict comparison (faster and clearer).
        //   Before: if (is_null($user)) {
        //   After:  if (null === $user) {
        'is_null' => true,

        // Wrap variables inside double-quoted strings in braces.
        //   Before: "Hello $name"
        //   After:  "Hello {$name}"
        'explicit_string_variable' => true,

        // Escape "implicit" backslashes in double-quoted strings. In "\d", PHP
        // keeps \d as is because it isn't a valid escape, but it's ambiguous.
        // Very useful in regular expressions.
        //   Before: preg_match("/\d+/", $text);
        //   After:  preg_match("/\\d+/", $text);
        'string_implicit_backslashes' => true,

        // Use single quotes when the string has no variables or escapes.
        //   Before: $status = "pending";
        //   After:  $status = 'pending';
        'single_quote' => true,

        // Replace conversion functions with casts (faster).
        //   intval($value)    ->  (int) $value
        //   floatval($value)  ->  (float) $value
        //   strval($value)    ->  (string) $value
        //   boolval($value)   ->  (bool) $value
        'modernize_types_casting' => true,

        // Replace strpos() compared with false by the PHP 8 functions.
        //   strpos($email, '@') !== false  ->  str_contains($email, '@')
        //   strpos($path, '/api') === 0    ->  str_starts_with($path, '/api')
        'modernize_strpos' => true,

        // Replace get_class($object) with the ::class syntax.
        //   Before: $class = get_class($task);
        //   After:  $class = $task::class;
        'get_class_to_class_keyword' => true,

        // If a parameter defaults to null, mark its type as nullable.
        // Important: since PHP 8.4, the version without "?" is deprecated.
        //   Before: public function __construct(string $description = null)
        //   After:  public function __construct(?string $description = null)
        'nullable_type_declaration_for_default_null_value' => true,

        // Replace the short "<?=" tag with "<?php echo".
        // Only affects templates written in PHP; since you use Twig, it barely applies.
        'echo_tag_syntax' => true,

        // Turn heredoc into nowdoc when the text has no variables.
        //   Before: $sql = <<<SQL
        //               SELECT * FROM task
        //               SQL;
        //   After:  $sql = <<<'SQL'
        //               SELECT * FROM task
        //               SQL;
        'heredoc_to_nowdoc' => true,

        // =====================================================================
        // WHITESPACE, OPERATORS AND BLANK LINES
        // =====================================================================

        // Align "=" in consecutive assignments and "=>" in arrays. Every other
        // operator gets one space on each side.
        //   Before: $id = 1;
        //           $userName = 'Ana';
        //   After:  $id       = 1;
        //           $userName = 'Ana';
        // [NOTE] When you add a variable with a longer name, every line in the
        // block gets realigned, which makes Git diffs bigger.
        'binary_operator_spaces' => ['operators' => ['=' => 'align_single_space', '=>' => 'align_single_space']],

        // The file ends with exactly one newline.
        'single_blank_line_at_eof' => true,

        // One space on each side of the concatenation operator.
        //   Before: 'Task '.$id
        //   After:  'Task ' . $id
        'concat_space' => ['spacing' => 'one'],

        // Add a blank line before return, throw, try, break, continue and
        // declare (the rule's default statements).
        //   Before: $total = $a + $b;
        //           return $total;
        //   After:  $total = $a + $b;
        //
        //           return $total;
        'blank_line_before_statement' => true,

        // Add the missing ";" to the last statement before the PHP closing tag.
        // Only affects files that close the tag (templates).
        'semicolon_after_instruction' => true,

        // Remove extra blank lines in the listed contexts:
        //   extra       -> two or more consecutive blank lines
        //   use         -> blank lines between "use" statements
        //   return/throw/break/continue -> empty lines right after them
        //   *_block     -> empty lines right inside ( ), [ ] and { }
        //   Before: $a = 1;
        //
        //
        //           $b = 2;
        //   After:  $a = 1;
        //
        //           $b = 2;
        'no_extra_blank_lines' => ['tokens' => ['extra', 'break', 'continue', 'return', 'throw', 'use', 'parenthesis_brace_block', 'square_brace_block', 'curly_brace_block']],

        // Remove spaces inside brackets when accessing an array.
        //   Before: $data[ 'userId' ]
        //   After:  $data['userId']
        'no_spaces_around_offset' => true,

        // One space after a cast.
        //   Before: (int)$value
        //   After:  (int) $value
        'cast_spaces' => ['space' => 'single'],

        // Lowercase casts.
        //   Before: (INT) $value
        //   After:  (int) $value
        'lowercase_cast' => true,

        // Short form of casts.
        //   (integer) -> (int)
        //   (boolean) -> (bool)
        'short_scalar_cast' => true,

        // Remove the space between "?" and the nullable type.
        //   Before: ? string $name
        //   After:  ?string $name
        'compact_nullable_type_declaration' => true,

        // Indent chained calls one level deeper than the first line.
        //   Before: $qb->select('t')
        //           ->from(Task::class, 't')
        //           ->where('t.id = :id');
        //   After:  $qb->select('t')
        //               ->from(Task::class, 't')
        //               ->where('t.id = :id');
        'method_chaining_indentation' => true,

        // [NEW] In multi-line chained calls, put the closing ";" on its own line.
        // It's the style this very file already uses, and adding a new call at
        // the end of the chain only changes one line in the diff.
        //   Before: $qb->select('t')
        //               ->where('t.id = :id');
        //   After:  $qb->select('t')
        //               ->where('t.id = :id')
        //           ;
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],

        // [NEW] Trailing comma on the last element of every multi-line list.
        // Adds "arguments" to what @Symfony already does (arrays, parameters,
        // match, destructuring). Adding an element then only changes one line.
        //   Before: new AssignTaskToUserCommand(
        //               $id,
        //               $data->userId
        //           );
        //   After:  new AssignTaskToUserCommand(
        //               $id,
        //               $data->userId,
        //           );
        'trailing_comma_in_multiline' => ['after_heredoc' => true, 'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters']],

        // =====================================================================
        // UNNECESSARY CODE
        // =====================================================================

        // Remove "else" when the "if" already ends with return or throw.
        //   Before: if (null === $task) {
        //               throw new TaskNotFoundException();
        //           } else {
        //               return $task;
        //           }
        //   After:  if (null === $task) {
        //               throw new TaskNotFoundException();
        //           }
        //
        //           return $task;
        'no_useless_else' => true,

        // Remove an empty "return;" at the end of a function, which does nothing.
        //   Before: public function save(Task $task): void
        //           {
        //               $this->em->persist($task);
        //               return;
        //           }
        //   After:  (without the return)
        'no_useless_return' => true,

        // Turn "elseif" into "if" when the previous block already exits with
        // return or throw.
        //   Before: if ($a) { return 1; } elseif ($b) { return 2; }
        //   After:  if ($a) { return 1; } if ($b) { return 2; }
        'no_superfluous_elseif' => true,

        // Remove braces that aren't part of any structure (standalone blocks).
        //   Before: { $a = 1; }
        //   After:  $a = 1;
        'no_unneeded_braces' => true,

        // Remove default values that can never be used: a parameter with a
        // default followed by a required one.
        //   Before: function assign($taskId = null, $userId)
        //   After:  function assign($taskId, $userId)
        'no_unreachable_default_argument_value' => true,

        // Remove sprintf() when there's nothing to format.
        //   Before: $message = sprintf('Task not found');
        //   After:  $message = 'Task not found';
        'no_useless_sprintf' => true,

        // Disabled: with false, spaces inside parentheses are left untouched.
        // [NOTE] @Symfony enables this rule to remove those spaces
        // (foo( $a ) -> foo($a)). Setting it to false stops that fix. You
        // probably want to delete this line to get @Symfony's behaviour back.
        'spaces_inside_parentheses' => false,

        // Remove whitespace at the end of lines.
        'no_trailing_whitespace' => true,

        // Remove whitespace on blank lines.
        'no_whitespace_in_blank_line' => true,

        // Remove line breaks around "=>" in arrays.
        //   Before: 'status'
        //               => 'done',
        //   After:  'status' => 'done',
        'no_multiline_whitespace_around_double_arrow' => true,

        // Remove whitespace before commas in arrays.
        //   Before: [1 , 2 , 3]
        //   After:  [1, 2, 3]
        'no_whitespace_before_comma_in_array' => true,

        // Remove blank lines right after a class opens.
        //   Before: class Foo
        //           {
        //
        //               private int $id;
        //   After:  class Foo
        //           {
        //               private int $id;
        'no_blank_lines_after_class_opening' => true,

        // Remove the space between a function name and its parenthesis.
        //   Before: count ($items)
        //   After:  count($items)
        'no_spaces_after_function_name' => true,

        // Remove spaces around "::".
        //   Before: Response :: HTTP_OK
        //   After:  Response::HTTP_OK
        'no_space_around_double_colon' => true,

        // =====================================================================
        // PHPDOC AND COMMENTS
        // =====================================================================

        // Order docblock tags: @param, then @throws, then @return.
        //   Before: /**
        //            * @return Task
        //            * @param string $id
        //            */
        //   After:  /**
        //            * @param string $id
        //            *
        //            * @return Task
        //            */
        'phpdoc_order' => true,

        // [NEW] Order @param tags to match the order of the function's parameters.
        //   Before: /**
        //            * @param string $userId
        //            * @param string $taskId
        //            */
        //           function assign($taskId, $userId)
        //   After:  /**
        //            * @param string $taskId
        //            * @param string $userId
        //            */
        'phpdoc_param_order' => true,

        // Separate different tag groups with a blank line (all @param together,
        // an empty line, then @return).
        'phpdoc_separation' => true,

        // Disabled. If enabled, it would align the types, variables and
        // descriptions of @param tags in columns.
        //   With true:  @param string $id     Task ID
        //               @param int    $limit  Max results
        'phpdoc_align' => false,

        // Add missing @param tags to an existing docblock, only for parameters
        // without a native type (adds nothing if every parameter is typed).
        //   Before: /**
        //            * Finds a task.
        //            */
        //           function find($id)
        //   After:  /**
        //            * Finds a task.
        //            *
        //            * @param mixed $id
        //            */
        'phpdoc_add_missing_param_annotation' => true,

        // Short form of scalar types in phpdoc.
        //   @param integer $id   ->  @param int $id
        //   @param boolean $done ->  @param bool $done
        //   @return double       ->  @return float
        'phpdoc_scalar' => true,

        // Fix the casing of types in phpdoc.
        //   @param Int|NULL $id  ->  @param int|null $id
        'phpdoc_types' => true,

        // Sort types alphabetically, with null always last.
        //   @param string|null|int $value  ->  @param int|string|null $value
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'alpha'],

        // [NEW] Use the generic array syntax that PHPStan understands best.
        // Risky only because it changes how the type is written; PHPStan reads
        // both, but array<int, Task> lets you specify the key type too.
        //   Before: @return Task[]
        //   After:  @return array<Task>
        'phpdoc_array_type' => true,

        // [NEW] Single-line docblocks for properties and constants when they fit.
        //   Before: /**
        //            * @var array<Task>
        //            */
        //           private array $tasks;
        //   After:  /** @var array<Task> */
        //           private array $tasks;
        'phpdoc_line_span' => ['const' => 'single', 'property' => 'single', 'method' => 'multi'],

        // Remove empty lines at the start and end of a docblock.
        'phpdoc_trim' => true,

        // Indent the docblock at the same level as the code it documents.
        'phpdoc_indent' => true,

        // Remove "@return void" and "@return null", which add no information.
        'phpdoc_no_empty_return' => true,

        // On properties, remove the variable name from @var (it's redundant).
        //   Before: /** @var int $count */
        //           private $count;
        //   After:  /** @var int */
        //           private $count;
        'phpdoc_var_without_name' => true,

        // Disabled. @Symfony turns docblocks that don't document a class,
        // method or property into regular comments (/* ... */). With false they
        // are kept, which is required for inline annotations like
        //   /** @var AssignTaskToUserDto $data */
        // that PHPStan and your IDE rely on (you use them in your controllers).
        'phpdoc_to_comment' => false,

        // Single-line comments always use "//".
        //   Before: # Comment       or   /* Comment */
        //   After:  // Comment
        'single_line_comment_style' => true,

        // Disabled. @Symfony removes @param and @return tags that only repeat
        // the native type. With false they are kept.
        //   With true:  /** @param string $id */ function find(string $id)
        //               -> the @param is removed because it adds nothing
        'no_superfluous_phpdoc_tags' => false,

        // Remove these tags from docblocks (Git history already says who wrote
        // each thing).
        //   Before: /**
        //            * @author Iván
        //            */
        //   After:  (docblock removed if it ends up empty)
        'general_phpdoc_annotation_remove' => ['annotations' => ['author', 'copyright', 'license']],

        // Remove blank lines between a docblock and what it documents.
        //   Before: /** Task controller */
        //
        //           final class TaskController
        //   After:  /** Task controller */
        //           final class TaskController
        'no_blank_lines_after_phpdoc' => true,

        // Remove empty docblocks: /** */
        'no_empty_phpdoc' => true,

        // Remove empty comments: //  or  /* */
        'no_empty_comment' => true,

        // Remove whitespace at the end of lines inside comments and docblocks.
        'no_trailing_whitespace_in_comment' => true,

        // =====================================================================
        // PHPUNIT (tests)
        // =====================================================================

        // Use strict assertions: compare the type too, not only the value.
        // Risky: a test that passed with "1" == 1 may start failing.
        //   Before: $this->assertEquals(200, $response->getStatusCode());
        //   After:  $this->assertSame(200, $response->getStatusCode());
        'php_unit_strict' => true,

        // Use the specific assertion instead of comparing with a fixed value.
        //   $this->assertSame(true, $result)   ->  $this->assertTrue($result)
        //   $this->assertSame(null, $task)     ->  $this->assertNull($task)
        'php_unit_construct' => true,

        // Use the dedicated assertion instead of a function inside assertTrue.
        // Failure messages become much clearer.
        //   $this->assertTrue(is_null($task))       ->  $this->assertNull($task)
        //   $this->assertTrue(file_exists($path))   ->  $this->assertFileExists($path)
        'php_unit_dedicate_assert' => true,

        // Replace the old expected-exception API with the current one.
        //   Before: $this->setExpectedException(TaskNotFoundException::class);
        //   After:  $this->expectException(TaskNotFoundException::class);
        // [NOTE] That old code no longer exists in PHPUnit 13, so this barely applies.
        'php_unit_expectation' => true,

        // Test method names in snake_case.
        //   Before: public function testItAssignsTaskToUser(): void
        //   After:  public function test_it_assigns_task_to_user(): void
        'php_unit_method_casing' => ['case' => 'snake_case'],

        // Fully qualified class name (with "\") in annotations like @covers.
        //   Before: @covers AssignTaskController
        //   After:  @covers \AssignTaskController
        // [NOTE] PHPUnit 13 only supports attributes (#[CoversClass(...)]), not
        // annotations, so this rule does nothing in your project.
        'php_unit_fqcn_annotation' => true,

        // [NEW] Convert PHPUnit annotations into attributes. PHPUnit 12+ no
        // longer reads annotations at all: a @dataProvider in a docblock is
        // silently ignored and the test runs without data. This rule fixes
        // any leftover (old code, copied snippets, generated tests).
        //   Before: /**
        //            * @dataProvider provideInvalidIds
        //            */
        //           public function test_it_rejects_invalid_ids(string $id): void
        //   After:  #[DataProvider('provideInvalidIds')]
        //           public function test_it_rejects_invalid_ids(string $id): void
        'php_unit_attributes' => true,

        // [NEW] Data providers must be static since PHPUnit 10. Makes them
        // static when they don't use $this.
        //   Before: public function provideInvalidIds(): iterable
        //   After:  public static function provideInvalidIds(): iterable
        'php_unit_data_provider_static' => true,

        // [NEW] Data providers always declare an iterable return type.
        //   Before: public static function provideInvalidIds()
        //   After:  public static function provideInvalidIds(): iterable
        'php_unit_data_provider_return_type' => true,

        // [NEW] Call assertions the same way in every test. PHPUnit assertions
        // are static methods, so "self::" is the most accurate form.
        // Options: 'self' (self::assertSame), 'this' ($this->assertSame),
        // 'static' (static::assertSame).
        //   Before: $this->assertSame(200, $status);
        //   After:  self::assertSame(200, $status);
        'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
    ])
    ->setFinder($finder)
    ;
