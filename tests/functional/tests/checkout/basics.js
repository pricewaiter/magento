module.exports = () => {
    ['POST', 'PUT', 'DELETE'].forEach(method => {
        it(`redirects to homepage for ${method} request`);
    });

    it('redirects to homepage for invalid deal id');

    it('redirects to homepage for revoked deal id');
};
