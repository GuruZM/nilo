import {
    queryParams,
    type RouteDefinition,
    type RouteFormDefinition,
    type RouteQueryOptions,
} from './../../wayfinder';
/**
 * @see \App\Http\Controllers\CompanyController::index
 * @see app/Http/Controllers/CompanyController.php:18
 * @route '/companies'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/companies',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\CompanyController::index
 * @see app/Http/Controllers/CompanyController.php:18
 * @route '/companies'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\CompanyController::index
 * @see app/Http/Controllers/CompanyController.php:18
 * @route '/companies'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CompanyController::index
 * @see app/Http/Controllers/CompanyController.php:18
 * @route '/companies'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\CompanyController::index
 * @see app/Http/Controllers/CompanyController.php:18
 * @route '/companies'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CompanyController::index
 * @see app/Http/Controllers/CompanyController.php:18
 * @route '/companies'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CompanyController::index
 * @see app/Http/Controllers/CompanyController.php:18
 * @route '/companies'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;

/**
 * @see \App\Http\Controllers\CompanyController::switchMethod
 * @see app/Http/Controllers/CompanyController.php:32
 * @route '/companies/switch'
 */
export const switchMethod = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: switchMethod.url(options),
    method: 'post',
});

switchMethod.definition = {
    methods: ['post'],
    url: '/companies/switch',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CompanyController::switchMethod
 * @see app/Http/Controllers/CompanyController.php:32
 * @route '/companies/switch'
 */
switchMethod.url = (options?: RouteQueryOptions) => {
    return switchMethod.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\CompanyController::switchMethod
 * @see app/Http/Controllers/CompanyController.php:32
 * @route '/companies/switch'
 */
switchMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: switchMethod.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CompanyController::switchMethod
 * @see app/Http/Controllers/CompanyController.php:32
 * @route '/companies/switch'
 */
const switchMethodForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: switchMethod.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CompanyController::switchMethod
 * @see app/Http/Controllers/CompanyController.php:32
 * @route '/companies/switch'
 */
switchMethodForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: switchMethod.url(options),
    method: 'post',
});

switchMethod.form = switchMethodForm;

const companies = {
    index: Object.assign(index, index),
    switch: Object.assign(switchMethod, switchMethod),
};

export default companies;
