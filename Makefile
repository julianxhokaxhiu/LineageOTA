###############################################################################
# ENVIRONMENT CONFIGURATION
###############################################################################
MAKEFLAGS += --no-print-directory
SHELL=/bin/bash

# Use default as default goal when running 'make'
.PHONY: default
.DEFAULT_GOAL := default

###############################################################################
# GOAL PARAMETERS
###############################################################################

# Container name
CONTAINER_NAME ?= "julianxhokaxhiu/lineageota"

# Tag name
TAG_NAME ?= "latest"

###############################################################################
# GOALS ( safe defaults )
###############################################################################

default:
	@docker build -t $(CONTAINER_NAME):$(TAG_NAME) .

run:
	@docker run --rm=true -it -p 8080:80 -v "$(CURDIR)/builds:/var/www/html/builds" $(CONTAINER_NAME):$(TAG_NAME)

clean:
	@docker rmi $(CONTAINER_NAME):$(TAG_NAME)
