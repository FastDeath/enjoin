require('dotenv').load();
var gulp = require('gulp');
var phpunit = require('gulp-phpunit');
var async = require('async');
var debug = require('debug')('gulp');
var lib = require('./test/js');
var createTables = require('./test/js/create-tables');

var testList = [
    'testModelCreate',
    'testModelCreateEmpty',
    'testModelCreateWithDateField',

    'testModelFindById',

    'testModelFindOneEager',
    'testModelFindOneEagerRequired',
    'testModelFindOneEagerById',
    'testModelFindOneEagerByIdRequired',
    'testModelFindOneEagerByIdMean',
    'testModelFindOneEagerMean',
    'testModelFindOneEagerMeanRequired',

    'testModelFindOneEagerReversed',
    'testModelFindOneEagerReversedRequired',
    'testModelFindOneEagerReversedById',
    'testModelFindOneEagerReversedByIdRequired',
    'testModelFindOneEagerReversedByIdMean',
    'testModelFindOneEagerReversedMean',
    'testModelFindOneEagerReversedMeanRequired',

    'testModelFindOneComplex',
    'testModelFindOneAndOr',

    'testModelFindOneEagerMulti',
    'testModelFindOneEagerMultiRequired',
    'testModelFindOneEagerMultiWhere',

    'testModelFindOneEagerNested',
    'testModelFindOneEagerNestedById',
    'testModelFindOneEagerNestedMean',
    'testModelFindOneEagerNestedDeep',
    'testModelFindOneEagerSelfNestedNoSubQuery',

    'testModelFindAll',
    'testModelFindAllEmptyList',
    'testModelFindAllEagerOneThenMany',
    'testModelFindAllEagerOneThenManyMean',
    'testModelFindAllEagerOneThenManyMeanOrdered',
    'testModelFindAllEagerOneThenManyMeanGrouped',
    'testModelFindAllEagerNestedDeep',
    'testModelFindAllEagerNestedDeepLimited',

    'testModelCount',
    'testModelCountConditional',
    'testModelCountEagerOneThenMany',
    'testModelCountEagerOneThenManyMean',
    'testModelCountEagerRequired',
    'testModelCountEagerRequiredLimited',

    'testModelFindAndCountAll',
    'testModelFindAndCountAllConditional',
    'testModelFindAndCountAllEagerOneThenMany',
    'testModelFindAndCountAllEagerOneThenManyMean',
    'testModelFindAndCountAllEagerRequired',
    'testModelFindAndCountAllEagerRequiredLimited',

    'testModelDestroy',
    'testModelUpdate'
];

gulp.task('create-tables', function (callback) {
    return createTables(callback);
});

testList.forEach(function (task) {
    gulp.task(task, ['create-tables'], function () {
        lib[task](function () {
        });
    });
});

gulp.task('phpunit', ['create-tables'], phpUnit);

gulp.task('test-all', testAll);

gulp.task('default', ['phpunit']);

function phpUnit() {
    gulp.src('phpunit.xml')
        .pipe(phpunit());
}

function testAll() {
    async.eachSeries(testList, function (task, done) {
        createTables(function () {
            debug('Run ' + task + '...');
            lib[task](function () {
                done();
            });
        });
    }, function (err) {
        if (err) {
            debug(err);
        }
    });
}
