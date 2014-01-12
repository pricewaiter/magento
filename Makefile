NO_COLOR=\x1b[0m
PASSED_COLOR=\x1b[32;01m
ERROR_COLOR=\x1b[31;01m

PASSED_STRING=$(PASSED_COLOR)[Passed]$(NO_COLOR)
ERROR_STRING=$(ERROR_COLOR)[Failed]$(NO_COLOR)
DONE_STRING=$(PASSED_COLOR)[Done]$(NO_COLOR)

check_log_file = @if test -s temp.log; then echo "$(ERROR_STRING)" && cat temp.log && touch temp.errors; else echo "$(PASSED_STRING)"; fi;
clear_no_syntax_errors = @sed -e '/^No syntax error/d' temp.log > temp.errs; mv temp.errs temp.log

all: clean
	@printf "Building package... "
	@php build.php &> temp.log
	@echo "$(DONE_STRING)"
	@printf "Checking for build problems... "
	$(call check_log_file)
	@printf "Cleaning up... "
	@rm -rf var/ temp.log temp.errors
	@echo "$(DONE_STRING)"

clean: test
	@printf "Deleting existing package file and .un~ files created by vim... "
	@find . -name "*.un~" -delete
	@rm -f nypwidget-*tgz
	@echo "$(DONE_STRING)"

test:
	@printf "Checking for syntax errors in XML files... "
	@find . -name "*.xml" -exec xmllint --noout {} \; 2> temp.log
	$(call check_log_file)
	@printf "Checking for syntax errors in PHP files... "
	@find . -name "*.php" -exec php -l {} \; &> temp.log
	$(call clear_no_syntax_errors)
	$(call check_log_file)
	@printf "Checking for syntax errors in PHTML files... "
	@find . -name "*.phtml" -exec php -l {} \; &> temp.log
	$(call clear_no_syntax_errors)
	$(call check_log_file)
	@printf "Checking for short tags in PHP files... "
	@find . -name "*.php" -exec grep -H '<?[^p]' {} \; 2> temp.log
	$(call check_log_file)
	@printf "Checking for short tags in PHTML files... "
	@find . -name "*.phtml" -exec grep -H '<?[^p]' {} \; 2> temp.log
	$(call check_log_file)
	@if test -e temp.errors; then false; fi;
	@rm -f temp.log temp.errors
