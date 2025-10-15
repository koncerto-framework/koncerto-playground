all: koncerto

.ONESHELL:
koncerto:
	cd koncerto-framework && php make.php
	cp koncerto-framework/dist/koncerto.php koncerto.php
	cd koncerto-impulsus && php make.php
	cp koncerto-impulsus/dist/impulsus.js impulsus.js
