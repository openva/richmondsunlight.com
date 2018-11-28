# Richmond Sunlight

[![Code Climate](https://codeclimate.com/github/openva/richmondsunlight.com/badges/gpa.svg)](https://codeclimate.com/github/openva/richmondsunlight.com) [![Build Status](https://travis-ci.org/openva/richmondsunlight.com.svg?branch=deploy)](https://travis-ci.org/openva/richmondsunlight.com)

This is the front-end of the website.  See also: [rs-machine](https://github.com/openva/rs-machine), the collection of scrapers and parsers that provide the site's third-party data, [rs-api](https://github.com/openva/rs-api), the API that powers (some of) the website, and [rs-video-processor](https://github.com/openva/rs-video-processor), the on-demand legislative-video-processing system.

## History

Richmond Sunlight started in 2005 as [a little RSS-based bill tracker](http://waldo.jaquith.org/bills/), updating every few hours. In 2006 it was built out as Richmond Sunlight, launching publicly in January of 2007. It's remained a hobby site ever since. The code base hasn’t been overhauled in all that time, and it shows — the site’s tech stack shows the growth rings of being developed over the course of many years. But it continues to function, and has been modernized in some ways, e.g. by adding a CI/CD pipeline, moving to SOA, etc.

## Branches

* [`master`](https://github.com/openva/richmondsunlight.com/tree/master): The staging site.
* [`deploy`](https://github.com/openva/richmondsunlight.com/tree/deploy): The live site.

## Architecture
![Network diagram](https://gist.githubusercontent.com/waldoj/b86e65bd8a14609849badefb85984ebf/raw/58012252ed5564fe6cf4b479df3fe8e2599786b9/rs_architecture.svg?sanitize=true)
