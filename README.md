# SDDI - Security Components as Open Source
The contents of the sub-directories is the deliverable for the "Werkvertrag" between the Technische University Munich and Secure Dimensions GmbH dated 28.10.2019.

### Note: This repository is currently being tested and subject to change.

The software is provided "AS IS" as expressed in the attached LICENSE.

* [AS](AS/authorization-server/README.md): This directory contains the Authorization Server as Open Source based on the MIT license and installation instructions to deploy the software.
* [DS](DS/README.md): This directory contains the documentations how to setup the IdP Discovery Service (WAYF) based on the WAYF developed by SWITCH.
* [RS](RS/README.md): This directory contains the software and installation instructions for operating the WFS endpoint using Bearer Token protection.
* [SP](SP/README.md): This directory contains the software ad installation instructions to setup the SOS1 and SOS2 using HTTP Cookie and Bearer Token protection.

The API description of the Authorization Server is avialable as OpenAPI located in the `/api/` path of the deployed Authorization Server.

The test cases for ensuring the correct functioning of the Authorization Server are available from the [test case documentation](AS/authorization-server/test/AS/TEST.md) file. That documentation also contains a description how to execute the tests with an Authorization Server deployment.


31.12.2019 - Secure Dimensions GmbH

